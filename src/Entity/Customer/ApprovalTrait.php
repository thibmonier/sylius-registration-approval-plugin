<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer;

use Doctrine\ORM\Mapping as ORM;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalDecision;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;

/**
 * Adds the approval status to the customer entity of the application:
 *
 *     #[ORM\Entity]
 *     #[ORM\Table(name: 'sylius_customer')]
 *     class Customer extends BaseCustomer implements ApprovalAwareInterface
 *     {
 *         use ApprovalTrait;
 *     }
 *
 * Four additive columns; existing customers stay approved.
 */
trait ApprovalTrait
{
    #[ORM\Column(name: 'approval_status', type: 'string', length: 20, options: ['default' => 'approved'])]
    protected string $approvalStatus = 'approved';

    #[ORM\Column(name: 'approval_decided_at', type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $approvalDecidedAt = null;

    #[ORM\Column(name: 'approval_decided_by', type: 'string', length: 255, nullable: true)]
    protected ?string $approvalDecidedBy = null;

    #[ORM\Column(name: 'approval_reason', type: 'string', length: 500, nullable: true)]
    protected ?string $approvalReason = null;

    public function getApprovalStatus(): ApprovalStatus
    {
        // A value this version does not know must not grant access.
        return ApprovalStatus::tryFrom($this->approvalStatus) ?? ApprovalStatus::Pending;
    }

    public function markApprovalPending(): void
    {
        $this->approvalStatus = ApprovalStatus::Pending->value;
        $this->approvalDecidedAt = null;
        $this->approvalDecidedBy = null;
        $this->approvalReason = null;
    }

    public function decideApproval(ApprovalStatus $status, ApprovalDecision $decision): void
    {
        $this->approvalStatus = $status->value;
        $this->approvalDecidedAt = $decision->decidedAt;
        $this->approvalDecidedBy = $decision->decidedBy;
        $this->approvalReason = $decision->reason;
    }

    public function getApprovalDecision(): ?ApprovalDecision
    {
        if (null === $this->approvalDecidedAt || null === $this->approvalDecidedBy) {
            return null;
        }

        return new ApprovalDecision($this->approvalDecidedAt, $this->approvalDecidedBy, $this->approvalReason);
    }
}
