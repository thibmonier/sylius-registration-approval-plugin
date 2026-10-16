<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Unit\Access;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Channel;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Access\ApprovalAwareAccessModeResolver;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\VisitorApprovalInterface;

final class ApprovalAwareAccessModeResolverTest extends TestCase
{
    /** @return iterable<string, array{AccessMode, bool, ?ApprovalStatus, bool, bool, bool}> mode, signed in, restriction, signed in (decorated), catalogue, prices */
    public static function matrix(): iterable
    {
        yield 'closed, approved customer' => [AccessMode::Closed, true, null, true, true, true];
        yield 'closed, pending customer' => [AccessMode::Closed, true, ApprovalStatus::Pending, false, false, false];
        yield 'closed, rejected customer' => [AccessMode::Closed, true, ApprovalStatus::Rejected, false, false, false];
        yield 'prices hidden, pending customer' => [AccessMode::PricesHidden, true, ApprovalStatus::Pending, false, true, false];
        yield 'open, pending customer' => [AccessMode::Open, true, ApprovalStatus::Pending, false, true, true];
        yield 'closed, anonymous' => [AccessMode::Closed, false, null, false, false, false];
        yield 'prices hidden, anonymous' => [AccessMode::PricesHidden, false, null, false, true, false];
    }

    #[DataProvider('matrix')]
    public function testWhatAVisitorSees(AccessMode $mode, bool $signedIn, ?ApprovalStatus $restriction, bool $expectedSignedIn, bool $catalogue, bool $prices): void
    {
        $inner = $this->createStub(AccessModeResolverInterface::class);
        $inner->method('currentMode')->willReturn($mode);
        $inner->method('isVisitorSignedIn')->willReturn($signedIn);
        $approval = $this->createStub(VisitorApprovalInterface::class);
        $approval->method('restrictedStatus')->willReturn($restriction);

        $resolver = new ApprovalAwareAccessModeResolver($inner, $approval);

        self::assertSame($mode, $resolver->currentMode());
        self::assertSame($expectedSignedIn, $resolver->isVisitorSignedIn());
        self::assertSame($catalogue, $resolver->canSeeCatalog());
        self::assertSame($prices, $resolver->canSeePrices());
    }

    public function testTheModeOfAChannelIsDelegated(): void
    {
        $channel = new Channel();
        $inner = $this->createMock(AccessModeResolverInterface::class);
        $inner->expects(self::once())->method('modeOf')->with($channel)->willReturn(AccessMode::Closed);

        self::assertSame(AccessMode::Closed, (new ApprovalAwareAccessModeResolver($inner, $this->createStub(VisitorApprovalInterface::class)))->modeOf($channel));
    }
}
