<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Approval;

use Sylius\Component\Core\Model\ChannelInterface;

final readonly class ConfiguredApprovalPolicy implements ApprovalPolicyInterface
{
    /** @param list<string> $channelCodes */
    public function __construct(private array $channelCodes)
    {
    }

    public function requiresApproval(ChannelInterface $channel): bool
    {
        return \in_array($channel->getCode(), $this->channelCodes, true);
    }
}
