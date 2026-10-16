<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Unit\Approval;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Channel;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ConfiguredApprovalPolicy;

final class ConfiguredApprovalPolicyTest extends TestCase
{
    public function testOnlyTheListedChannelsValidateTheirRegistrations(): void
    {
        $policy = new ConfiguredApprovalPolicy(['B2B_WEB']);

        self::assertTrue($policy->requiresApproval($this->channel('B2B_WEB')));
        self::assertFalse($policy->requiresApproval($this->channel('SHOP')));
        self::assertFalse($policy->requiresApproval($this->channel('b2b_web')), 'codes are case sensitive');
    }

    public function testNoChannelByDefault(): void
    {
        self::assertFalse((new ConfiguredApprovalPolicy([]))->requiresApproval($this->channel('B2B_WEB')));
    }

    private function channel(string $code): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);

        return $channel;
    }
}
