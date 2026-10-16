<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Approval;

interface VisitorApprovalInterface
{
    /**
     * The status of the signed-in customer when this channel validates registrations and the
     * customer is not approved (pending or rejected): such a customer is treated as anonymous.
     * Null for anybody else (anonymous, approved, channel without validation, no HTTP request).
     */
    public function restrictedStatus(): ?ApprovalStatus;
}
