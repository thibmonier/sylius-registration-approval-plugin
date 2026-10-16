<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Integration;

use Sylius\Component\Core\Model\Customer as CoreCustomer;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tests\Tmonier\SyliusRegistrationApprovalPlugin\RegistrationApprovalTestTrait;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\Event\ApprovalDecidedEvent;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\Exception\InvalidApprovalTransitionException;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\RegistrationApprovalManager;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\RegistrationApprovalManagerInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\ApprovalAwareInterface;

final class RegistrationApprovalManagerTest extends KernelTestCase
{
    use RegistrationApprovalTestTrait;

    /** @var list<ApprovalDecidedEvent> */
    private array $events = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginTestTransaction();
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(ApprovalDecidedEvent::class, function (ApprovalDecidedEvent $event): void {
            $this->events[] = $event;
        });
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    public function testApprovingStoresTheDecisionAndDispatchesTheEvent(): void
    {
        $customer = $this->pending('a@example.com');

        $this->manager()->approve($customer, 'sophie', '  VAT checked ');

        self::assertSame(ApprovalStatus::Approved, $this->statusOf('a@example.com'));
        $decision = $this->aware($this->customerByEmail('a@example.com'))->getApprovalDecision();
        self::assertNotNull($decision);
        self::assertSame('sophie', $decision->decidedBy);
        self::assertSame('VAT checked', $decision->reason);
        self::assertEqualsWithDelta(time(), $decision->decidedAt->getTimestamp(), 5);
        self::assertCount(1, $this->events);
        self::assertSame(ApprovalStatus::Approved, $this->events[0]->status);
        self::assertSame('sophie', $this->events[0]->decidedBy);
    }

    public function testRejectingStoresTheDecisionWithoutReason(): void
    {
        $customer = $this->pending('a@example.com');

        $this->manager()->reject($customer, 'sophie', '   ');

        self::assertSame(ApprovalStatus::Rejected, $this->statusOf('a@example.com'));
        self::assertNull($this->aware($this->customerByEmail('a@example.com'))->getApprovalDecision()?->reason);
        self::assertSame(ApprovalStatus::Rejected, $this->events[0]->status);
    }

    public function testADecisionIsNeverReplayed(): void
    {
        $customer = $this->pending('a@example.com');
        $this->manager()->approve($customer, 'sophie');

        foreach ([fn () => $this->manager()->reject($customer, 'paul'), fn () => $this->manager()->approve($customer, 'paul')] as $replay) {
            try {
                $replay();
                self::fail('A decided registration must not be decided again.');
            } catch (InvalidApprovalTransitionException) {
                // expected
            }
        }

        self::assertSame(ApprovalStatus::Approved, $this->statusOf('a@example.com'));
        self::assertSame('sophie', $this->aware($this->customerByEmail('a@example.com'))->getApprovalDecision()?->decidedBy);
        self::assertCount(1, $this->events, 'no event for a refused decision');
    }

    public function testAnApprovedCustomerCannotBeRejectedLater(): void
    {
        $customer = $this->account('b@example.com', ApprovalStatus::Approved)->getCustomer();
        self::assertInstanceOf(CustomerInterface::class, $customer);

        $this->expectException(InvalidApprovalTransitionException::class);
        $this->manager()->reject($customer, 'sophie');
    }

    public function testATooLongReasonIsRefusedAndNothingChanges(): void
    {
        $customer = $this->pending('a@example.com');

        try {
            $this->manager()->reject($customer, 'sophie', str_repeat('x', RegistrationApprovalManager::REASON_MAX_LENGTH + 1));
            self::fail('The reason is limited.');
        } catch (\InvalidArgumentException) {
            // expected
        }

        self::assertSame(ApprovalStatus::Pending, $this->statusOf('a@example.com'));
        self::assertSame([], $this->events);
    }

    public function testACustomerClassWithoutTheTraitCannotBeDecided(): void
    {
        $this->expectException(InvalidApprovalTransitionException::class);
        $this->manager()->approve(new CoreCustomer(), 'sophie');
    }

    private function pending(string $email): CustomerInterface
    {
        $customer = $this->account($email, ApprovalStatus::Pending)->getCustomer();
        self::assertInstanceOf(CustomerInterface::class, $customer);

        return $customer;
    }

    private function aware(?object $customer): ApprovalAwareInterface
    {
        self::assertInstanceOf(ApprovalAwareInterface::class, $customer);

        return $customer;
    }

    private function manager(): RegistrationApprovalManagerInterface
    {
        $manager = self::getContainer()->get(RegistrationApprovalManagerInterface::class);
        self::assertInstanceOf(RegistrationApprovalManagerInterface::class, $manager);

        return $manager;
    }
}
