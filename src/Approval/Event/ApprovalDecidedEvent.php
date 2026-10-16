<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Approval\Event;

use Sylius\Component\Core\Model\CustomerInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;

/** Dispatched once the decision is stored (listen to it to send e-mails, notify a CRM...). */
final readonly class ApprovalDecidedEvent
{
    public function __construct(
        public CustomerInterface $customer,
        public ApprovalStatus $status,
        public string $decidedBy,
        public ?string $reason,
    ) {
    }
}
