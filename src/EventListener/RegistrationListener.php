<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\EventListener;

use Doctrine\ORM\Event\PrePersistEventArgs;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\RegistrationMarker;
use Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\ApprovalAwareInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Form\Extension\RegistrationTypeExtension;

/**
 * Pending state for the registrations of the shop, whatever the entry point (registration page,
 * registration after checkout, shop API): the Doctrine `prePersist` of the new shop user. The
 * `sylius.customer.pre_register` event of the pages answers the duplicate e-mail case.
 */
final readonly class RegistrationListener
{
    private const THANK_YOU_ROUTE = 'sylius_shop_register_thank_you';

    public function __construct(
        private RegistrationMarker $marker,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onPreRegister(ResourceControllerEvent $event): void
    {
        if (true === $this->requestStack->getMainRequest()?->attributes->get(RegistrationTypeExtension::DUPLICATE_EMAIL_ATTRIBUTE)) {
            // The e-mail belongs to an account: nothing is created and the visitor gets the same
            // answer as after a real registration (message, page).
            $event->stop('sylius.customer.register', ResourceControllerEvent::TYPE_SUCCESS);
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate(self::THANK_YOU_ROUTE)));
        }
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->marker->markIfRegistration($args->getObject());
    }

    /**
     * Sylius signs the new customer in; on a channel that validates registrations the visitor is
     * signed out again, so that registering looks the same whether the e-mail was new or not (see
     * the duplicate e-mail handling) and the pending account is used on the next sign-in.
     */
    public function onPostRegister(ResourceControllerEvent $event): void
    {
        $customer = $event->getSubject();
        if ($customer instanceof ApprovalAwareInterface && ApprovalStatus::Pending === $customer->getApprovalStatus() && $this->requestStack->getMainRequest() instanceof Request) {
            $this->tokenStorage->setToken(null);
            // Same address as the duplicate e-mail answer (Sylius would add the customer id).
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate(self::THANK_YOU_ROUTE)));
        }
    }
}
