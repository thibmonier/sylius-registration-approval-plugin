<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Controller\Admin;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\Exception\InvalidApprovalTransitionException;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\RegistrationApprovalManagerInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\ApprovalAwareInterface;

/** Approves or rejects a pending registration: POST only, CSRF token, administration access. */
final readonly class DecisionController
{
    public const CSRF_PREFIX = 'tmonier_registration_decision_';

    public const ROLE = 'ROLE_ADMINISTRATION_ACCESS';

    /** @param RepositoryInterface<CustomerInterface> $customerRepository */
    public function __construct(
        private RepositoryInterface $customerRepository,
        private RegistrationApprovalManagerInterface $manager,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request, int $id, string $decision): RedirectResponse
    {
        if (!$this->security->isGranted(self::ROLE)) {
            throw new AccessDeniedHttpException();
        }

        $token = $request->request->get('_csrf_token');
        if (!\is_string($token) || !$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_PREFIX . $id, $token))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $customer = $this->customerRepository->find($id);
        if (!$customer instanceof CustomerInterface || !$customer instanceof ApprovalAwareInterface) {
            throw new NotFoundHttpException();
        }

        $flash = $this->decide($customer, $decision, $this->reason($request));

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($flash[0], $flash[1]);
        }

        return new RedirectResponse($this->urlGenerator->generate('tmonier_registration_approval_admin_index'));
    }

    /** @return array{string, string} flash type and translation key */
    private function decide(CustomerInterface $customer, string $decision, ?string $reason): array
    {
        $by = mb_substr((string) $this->security->getUser()?->getUserIdentifier(), 0, 255);

        try {
            match ($decision) {
                'approve' => $this->manager->approve($customer, $by, $reason),
                'reject' => $this->manager->reject($customer, $by, $reason),
                default => throw new NotFoundHttpException(),
            };
        } catch (InvalidApprovalTransitionException) {
            return ['warning', 'tmonier_registration_approval.flash.already_decided'];
        } catch (\InvalidArgumentException) {
            return ['error', 'tmonier_registration_approval.flash.reason_too_long'];
        }

        return ['success', 'approve' === $decision ? 'tmonier_registration_approval.flash.approved' : 'tmonier_registration_approval.flash.rejected'];
    }

    private function reason(Request $request): ?string
    {
        $reason = $request->request->get('reason');

        return \is_string($reason) ? $reason : null;
    }
}
