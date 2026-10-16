<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Approval;

/** The last decision taken on a registration. */
final readonly class ApprovalDecision
{
    public function __construct(
        public \DateTimeImmutable $decidedAt,
        public string $decidedBy,
        public ?string $reason,
    ) {
    }
}
