<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer;

use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalDecision;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;

/** Implemented by the customer entity of the application (see ApprovalTrait). */
interface ApprovalAwareInterface
{
    public function getApprovalStatus(): ApprovalStatus;

    /** Marks a new registration as awaiting a decision (clears any previous decision). */
    public function markApprovalPending(): void;

    public function decideApproval(ApprovalStatus $status, ApprovalDecision $decision): void;

    public function getApprovalDecision(): ?ApprovalDecision;
}
