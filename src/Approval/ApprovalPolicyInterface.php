<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Approval;

use Sylius\Component\Core\Model\ChannelInterface;

/** Which channels validate their registrations. */
interface ApprovalPolicyInterface
{
    public function requiresApproval(ChannelInterface $channel): bool;
}
