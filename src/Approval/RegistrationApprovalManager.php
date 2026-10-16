<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Approval;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\Event\ApprovalDecidedEvent;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\Exception\InvalidApprovalTransitionException;
use Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\ApprovalAwareInterface;

final readonly class RegistrationApprovalManager implements RegistrationApprovalManagerInterface
{
    public const REASON_MAX_LENGTH = 500;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function approve(CustomerInterface $customer, string $decidedBy, ?string $reason = null): void
    {
        $this->decide($customer, ApprovalStatus::Approved, $decidedBy, $reason);
    }

    public function reject(CustomerInterface $customer, string $decidedBy, ?string $reason = null): void
    {
        $this->decide($customer, ApprovalStatus::Rejected, $decidedBy, $reason);
    }

    private function decide(CustomerInterface $customer, ApprovalStatus $status, string $decidedBy, ?string $reason): void
    {
        if (!$customer instanceof ApprovalAwareInterface) {
            throw new InvalidApprovalTransitionException('The customer class does not implement ApprovalAwareInterface.');
        }
        $reason = null === $reason || '' === trim($reason) ? null : trim($reason);
        if (null !== $reason && mb_strlen($reason) > self::REASON_MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('The reason is limited to %d characters.', self::REASON_MAX_LENGTH));
        }

        // Two administrators deciding at once: the second one sees the first decision. A refusal is
        // reported after the transaction: an exception inside would close the entity manager.
        $decided = $this->entityManager->wrapInTransaction(function () use ($customer, $status, $decidedBy, $reason): bool {
            // Lock the customer row alone (a locking refresh joins associations, which PostgreSQL
            // refuses on the nullable side of an outer join), then read the committed state.
            $this->entityManager->lock($customer, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($customer);
            if (ApprovalStatus::Pending !== $customer->getApprovalStatus()) {
                return false;
            }

            $customer->decideApproval($status, new ApprovalDecision(new \DateTimeImmutable(), $decidedBy, $reason));

            return true;
        });

        if (!$decided) {
            throw new InvalidApprovalTransitionException(sprintf('The registration is already %s.', $customer->getApprovalStatus()->value));
        }

        $this->eventDispatcher->dispatch(new ApprovalDecidedEvent($customer, $status, $decidedBy, $reason));
    }
}
