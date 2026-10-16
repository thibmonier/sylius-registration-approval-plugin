<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tmonier\SyliusChannelAccessPlugin\EventListener\VisitorAccessListener;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\VisitorApprovalInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Shop\PendingPageRenderer;

/**
 * A signed-in customer who is pending or rejected is an anonymous visitor for the channel access
 * plugin. Where that plugin would send an anonymous visitor to the login (closed channel,
 * restricted routes), this customer gets the waiting page with a 403: a redirect to the login
 * page of someone already signed in would be misleading.
 */
final readonly class PendingCustomerListener
{
    public function __construct(
        private VisitorApprovalInterface $approval,
        private VisitorAccessListener $accessListener,
        private PendingPageRenderer $renderer,
    ) {
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $this->refusal($event->getRequest());
        if ($response instanceof Response) {
            $event->setResponse($response);
        }
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getThrowable() instanceof HttpExceptionInterface) {
            return;
        }

        $response = $this->refusal($event->getRequest());
        if ($response instanceof Response) {
            $event->setResponse($response);
        }
    }

    private function refusal(Request $request): ?Response
    {
        $status = $this->approval->restrictedStatus();
        if (!$status instanceof \Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus || !$this->accessListener->refusalFor($request) instanceof Response) {
            return null;
        }

        return $this->renderer->render($request, $status);
    }
}
