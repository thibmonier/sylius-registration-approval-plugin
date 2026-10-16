<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Controller\Shop;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\VisitorApprovalInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Shop\PendingPageRenderer;

/** The waiting page of a signed-in customer who is pending or rejected. */
final readonly class PendingController
{
    public function __construct(
        private VisitorApprovalInterface $approval,
        private PendingPageRenderer $renderer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $status = $this->approval->restrictedStatus();
        if (!$status instanceof \Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus) {
            // Anonymous or approved: nothing to wait for.
            return new RedirectResponse($this->urlGenerator->generate('sylius_shop_homepage'));
        }

        return $this->renderer->render($request, $status, Response::HTTP_OK);
    }
}
