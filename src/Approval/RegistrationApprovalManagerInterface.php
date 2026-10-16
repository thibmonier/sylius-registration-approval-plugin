<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Approval;

use Sylius\Component\Core\Model\CustomerInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\Exception\InvalidApprovalTransitionException;

/**
 * Takes the decision on a pending registration: stores it (status, date, author, optional
 * internal reason) and dispatches `ApprovalDecidedEvent`.
 */
interface RegistrationApprovalManagerInterface
{
    /**
     * @throws InvalidApprovalTransitionException when the customer is not pending
     * @throws \InvalidArgumentException           when the reason is longer than 500 characters
     */
    public function approve(CustomerInterface $customer, string $decidedBy, ?string $reason = null): void;

    /**
     * @throws InvalidApprovalTransitionException when the customer is not pending
     * @throws \InvalidArgumentException           when the reason is longer than 500 characters
     */
    public function reject(CustomerInterface $customer, string $decidedBy, ?string $reason = null): void;
}
