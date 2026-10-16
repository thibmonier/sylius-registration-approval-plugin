<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Integration;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\Tmonier\SyliusRegistrationApprovalPlugin\RegistrationApprovalTestTrait;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;

final class CheckCommandTest extends KernelTestCase
{
    use RegistrationApprovalTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    public function testAValidatingChannelInOpenModeIsReported(): void
    {
        $this->setMode(AccessMode::Open);

        $tester = $this->check();

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('FASHION_WEB', $tester->getDisplay());
        self::assertStringContainsString('"open"', $tester->getDisplay());
    }

    public function testRestrictingChannelsPass(): void
    {
        foreach ([AccessMode::PricesHidden, AccessMode::Closed] as $mode) {
            $this->setMode($mode);

            self::assertSame(0, $this->check()->getStatusCode(), $mode->value);
        }
    }

    private function kernel(): KernelInterface
    {
        self::assertNotNull(self::$kernel, 'booted in setUp');

        return self::$kernel;
    }

    private function check(): CommandTester
    {
        $application = new Application($this->kernel());
        $tester = new CommandTester($application->find('tmonier:registration-approval:check'));
        $tester->execute([]);

        return $tester;
    }
}
