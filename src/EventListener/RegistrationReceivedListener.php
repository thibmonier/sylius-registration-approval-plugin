<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\EventListener;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalPolicyInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Shop\PendingPageRenderer;

/**
 * The page every registration of a channel that validates registrations ends on (new account or
 * not, see the duplicate e-mail handling): "received, awaiting validation". Sylius would send the
 * visitor to the account dashboard, which needs a session the visitor does not have any more.
 */
final readonly class RegistrationReceivedListener
{
    private const string THANK_YOU_ROUTE = 'sylius_shop_register_thank_you';

    public function __construct(
        private ChannelContextInterface $channelContext,
        private ApprovalPolicyInterface $policy,
        private PendingPageRenderer $renderer,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || self::THANK_YOU_ROUTE !== $event->getRequest()->attributes->get('_route')) {
            return;
        }

        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return;
        }

        if ($channel instanceof ChannelInterface && $this->policy->requiresApproval($channel)) {
            $event->setResponse($this->renderer->renderReceived($event->getRequest()));
        }
    }
}
