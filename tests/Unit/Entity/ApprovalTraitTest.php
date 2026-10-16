<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Tests\Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\Customer;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalDecision;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;

final class ApprovalTraitTest extends TestCase
{
    public function testACustomerIsApprovedByDefaultWithoutDecision(): void
    {
        $customer = new Customer();

        self::assertSame(ApprovalStatus::Approved, $customer->getApprovalStatus());
        self::assertNull($customer->getApprovalDecision());
    }

    public function testADecisionIsRecorded(): void
    {
        $customer = new Customer();
        $at = new \DateTimeImmutable('2026-10-16 10:00:00');

        $customer->decideApproval(ApprovalStatus::Rejected, new ApprovalDecision($at, 'sophie', 'not a buyer'));

        self::assertSame(ApprovalStatus::Rejected, $customer->getApprovalStatus());
        self::assertEquals(new ApprovalDecision($at, 'sophie', 'not a buyer'), $customer->getApprovalDecision());
    }

    public function testMarkingPendingForgetsThePreviousDecision(): void
    {
        $customer = new Customer();
        $customer->decideApproval(ApprovalStatus::Approved, new ApprovalDecision(new \DateTimeImmutable(), 'sophie', null));

        $customer->markApprovalPending();

        self::assertSame(ApprovalStatus::Pending, $customer->getApprovalStatus());
        self::assertNull($customer->getApprovalDecision());
    }

    public function testAStoredValueThisVersionDoesNotKnowReadsAsPending(): void
    {
        $customer = new Customer();
        (new \ReflectionProperty($customer, 'approvalStatus'))->setValue($customer, 'vip');

        self::assertSame(ApprovalStatus::Pending, $customer->getApprovalStatus());
    }

    public function testTheStoredValuesAreThePublicContract(): void
    {
        self::assertSame(['pending', 'approved', 'rejected'], array_map(static fn (ApprovalStatus $status): string => $status->value, ApprovalStatus::cases()));
    }
}
