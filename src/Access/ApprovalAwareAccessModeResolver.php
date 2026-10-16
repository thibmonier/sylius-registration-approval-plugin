<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Access;

use Sylius\Component\Core\Model\ChannelInterface;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\VisitorApprovalInterface;

/**
 * Decorates the resolver of the channel access plugin: a customer awaiting approval (or
 * rejected) is not "signed in" for it, so every protection of that plugin (redirections, hidden
 * prices, refused add to cart, API) applies to the customer as it does to an anonymous visitor.
 * On an open channel nothing is restricted, whoever the customer is.
 */
final readonly class ApprovalAwareAccessModeResolver implements AccessModeResolverInterface
{
    public function __construct(
        private AccessModeResolverInterface $inner,
        private VisitorApprovalInterface $approval,
    ) {
    }

    public function modeOf(ChannelInterface $channel): AccessMode
    {
        return $this->inner->modeOf($channel);
    }

    public function currentMode(): AccessMode
    {
        return $this->inner->currentMode();
    }

    public function isVisitorSignedIn(): bool
    {
        return $this->inner->isVisitorSignedIn() && !$this->approval->restrictedStatus() instanceof \Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;
    }

    public function canSeeCatalog(): bool
    {
        return AccessMode::Closed !== $this->currentMode() || $this->isVisitorSignedIn();
    }

    public function canSeePrices(): bool
    {
        return AccessMode::Open === $this->currentMode() || $this->isVisitorSignedIn();
    }
}
