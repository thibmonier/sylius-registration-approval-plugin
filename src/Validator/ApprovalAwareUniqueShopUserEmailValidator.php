<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ChannelApprovalChecker;

/**
 * Decorates the "unique shop user e-mail" validator of the shop API registration: on a channel
 * that validates registrations the API must not say that an address is taken (see
 * ApprovalAwareRegisterShopUserHandler, which then creates nothing).
 */
final class ApprovalAwareUniqueShopUserEmailValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ConstraintValidatorInterface $inner,
        private readonly ChannelApprovalChecker $channelChecker,
    ) {
    }

    public function initialize(ExecutionContextInterface $context): void
    {
        parent::initialize($context);
        $this->inner->initialize($context);
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if ($this->channelChecker->currentChannelRequiresApproval()) {
            return;
        }

        $this->inner->validate($value, $constraint);
    }
}
