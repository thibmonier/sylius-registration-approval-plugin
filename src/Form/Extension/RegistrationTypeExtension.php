<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Form\Extension;

use Sylius\Bundle\ShopBundle\Form\Type\CustomerRegistrationType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ChannelApprovalChecker;

/**
 * No account enumeration: on a channel that validates registrations, "this e-mail is already
 * used" is not shown. The other errors of the form are, so that a visitor cannot tell an existing
 * address from a new one by filling the form wrongly. The registration listener then stops the
 * creation and answers like a successful registration.
 */
final class RegistrationTypeExtension extends AbstractTypeExtension
{
    /** Request attribute set when the submitted e-mail belongs to an account. */
    public const DUPLICATE_EMAIL_ATTRIBUTE = 'tmonier_registration_approval_duplicate_email';

    public function __construct(
        private readonly ChannelApprovalChecker $channelChecker,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [CustomerRegistrationType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // After the validation listener of the form component (priority 0).
        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->hideDuplicateEmail(...), -10);
    }

    private function hideDuplicateEmail(FormEvent $event): void
    {
        $request = $this->requestStack->getMainRequest();
        $form = $event->getForm();
        if (!$request instanceof \Symfony\Component\HttpFoundation\Request || !$form->isRoot() || !$form->has('email') || !$this->channelChecker->currentChannelRequiresApproval()) {
            return;
        }

        $email = $form->get('email');
        $kept = [];
        $duplicate = false;
        foreach ($email->getErrors() as $error) {
            if (!$error instanceof FormError) {
                continue;
            }
            if ($this->isUniqueViolation($error)) {
                $duplicate = true;

                continue;
            }
            $kept[] = $error;
        }

        if (!$duplicate || !$email instanceof Form) {
            return;
        }

        $email->clearErrors();
        foreach ($kept as $error) {
            $email->addError($error);
        }
        $request->attributes->set(self::DUPLICATE_EMAIL_ATTRIBUTE, true);
    }

    private function isUniqueViolation(FormError $error): bool
    {
        $cause = $error->getCause();

        return $cause instanceof ConstraintViolationInterface && UniqueEntity::NOT_UNIQUE_ERROR === $cause->getCode();
    }
}
