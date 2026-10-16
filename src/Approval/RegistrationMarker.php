<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Approval;

use Psr\Log\LoggerInterface;
use Sylius\Bundle\ApiBundle\SectionResolver\ShopApiSection;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Bundle\ShopBundle\SectionResolver\ShopSection;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\ApprovalAwareInterface;

/**
 * Puts the account created by a registration of the shop (page or shop API), on any channel, in
 * the pending state. Accounts created elsewhere (administration,
 * fixtures, console) and customers without account (guest checkout) are untouched.
 */
final readonly class RegistrationMarker
{
    public function __construct(
        private RequestStack $requestStack,
        private SectionProviderInterface $sectionProvider,
        private ChannelContextInterface $channelContext,
        private ApprovalPolicyInterface $policy,
        private AccessModeResolverInterface $accessModes,
        private ?LoggerInterface $logger = null,
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

        // Whatever the channel: registering on a channel that does not validate must not be a way
        // round the validation of the others. The restriction itself only applies on validating
        // channels (VisitorApproval).
        $customer->markApprovalPending();
        $this->warnIfValidationIsInert();
    }

    private function warnIfValidationIsInert(): void
    {
        $channel = $this->channel();
        if ($channel instanceof ChannelInterface && $this->policy->requiresApproval($channel) && AccessMode::Open === $this->accessModes->modeOf($channel)) {
            $this->logger?->warning('The channel "{channel}" validates registrations but its access mode is "open": pending customers are not restricted. Set the channel to "prices_hidden" or "closed".', ['channel' => $channel->getCode()]);
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
