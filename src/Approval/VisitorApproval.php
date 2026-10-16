<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Approval;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\ApprovalAwareInterface;

final readonly class VisitorApproval implements VisitorApprovalInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private ChannelContextInterface $channelContext,
        private RequestStack $requestStack,
        private ApprovalPolicyInterface $policy,
    ) {
    }

    public function restrictedStatus(): ?ApprovalStatus
    {
        if (!$this->requestStack->getMainRequest() instanceof \Symfony\Component\HttpFoundation\Request) {
            return null;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof ShopUserInterface) {
            return null;
        }

        $customer = $user->getCustomer();
        if (!$customer instanceof ApprovalAwareInterface || ApprovalStatus::Approved === $customer->getApprovalStatus()) {
            return null;
        }

        $channel = $this->channel();

        return $channel instanceof ChannelInterface && $this->policy->requiresApproval($channel) ? $customer->getApprovalStatus() : null;
    }

    private function channel(): ?ChannelInterface
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return null;
        }

        return $channel instanceof ChannelInterface ? $channel : null;
    }
}
