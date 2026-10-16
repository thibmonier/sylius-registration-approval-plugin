<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Approval;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;

/** Does the channel of the current request validate its registrations? */
final readonly class ChannelApprovalChecker
{
    public function __construct(
        private ChannelContextInterface $channelContext,
        private ApprovalPolicyInterface $policy,
    ) {
    }

    public function currentChannelRequiresApproval(): bool
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return false;
        }

        return $channel instanceof ChannelInterface && $this->policy->requiresApproval($channel);
    }
}
