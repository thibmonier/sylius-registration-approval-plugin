<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Approval\Exception;

/** Only a pending registration can be approved or rejected; a decision is never replayed. */
final class InvalidApprovalTransitionException extends \DomainException
{
}
