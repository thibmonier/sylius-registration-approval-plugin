<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Approval;

use Sylius\Bundle\ApiBundle\SectionResolver\ShopApiSection;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Bundle\ShopBundle\SectionResolver\ShopSection;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\ApprovalAwareInterface;

/**
 * Puts the account created by a registration of the shop (page or shop API) on a channel that
 * validates its registrations in the pending state. Accounts created elsewhere (administration,
 * fixtures, console) and customers without account (guest checkout) are untouched.
 */
final readonly class RegistrationMarker
{
    public function __construct(
        private RequestStack $requestStack,
        private SectionProviderInterface $sectionProvider,
        private ChannelContextInterface $channelContext,
        private ApprovalPolicyInterface $policy,
    ) {
    }

    /**
     * @param object $user the entity being persisted; only a new shop user (the account created by
     *                     the registration, whether its customer is new or already known from a
     *                     guest checkout) is considered
     */
    public function markIfRegistration(object $user): void
    {
        if (!$user instanceof ShopUserInterface) {
            return;
        }

        $customer = $user->getCustomer();
        if (!$customer instanceof ApprovalAwareInterface) {
            return;
        }

        if (!$this->requestStack->getMainRequest() instanceof \Symfony\Component\HttpFoundation\Request || !$this->isShopSection()) {
            return;
        }

        $channel = $this->channel();
        if ($channel instanceof ChannelInterface && $this->policy->requiresApproval($channel)) {
            $customer->markApprovalPending();
        }
    }

    private function isShopSection(): bool
    {
        $section = $this->sectionProvider->getSection();

        return $section instanceof ShopSection || $section instanceof ShopApiSection;
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
