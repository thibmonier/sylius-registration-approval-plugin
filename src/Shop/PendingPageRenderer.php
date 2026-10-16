<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Shop;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;
use Twig\Environment;

/** The waiting page, and the same refusal for the shop API. */
final readonly class PendingPageRenderer
{
    public function __construct(
        private Environment $twig,
        private string $apiPathPrefix,
        private ?LocaleAwareInterface $translator = null,
    ) {
    }

    public function render(Request $request, ApprovalStatus $status, int $httpStatus = Response::HTTP_FORBIDDEN): Response
    {
        if (str_starts_with($request->getPathInfo(), $this->apiPathPrefix . '/')) {
            return new JsonResponse(
                ['code' => $httpStatus, 'message' => ApprovalStatus::Rejected === $status ? 'Your registration was not approved.' : 'Your registration is awaiting approval.'],
                $httpStatus,
                ['Cache-Control' => 'private, no-store'],
            );
        }

        $this->useLocaleOf($request);
        $response = new Response($this->twig->render('@TmonierSyliusRegistrationApprovalPlugin/shop/pending.html.twig', ['status' => $status->value]), $httpStatus);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function renderReceived(?Request $request = null): Response
    {
        if ($request instanceof Request) {
            $this->useLocaleOf($request);
        }
        $response = new Response($this->twig->render('@TmonierSyliusRegistrationApprovalPlugin/shop/received.html.twig'));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /**
     * These pages are rendered early in the request (before the controller): the translator follows the
     * locale of the URL explicitly instead of relying on the order of the listeners.
     */
    private function useLocaleOf(Request $request): void
    {
        $locale = $request->attributes->get('_locale');
        if ($this->translator instanceof \Symfony\Contracts\Translation\LocaleAwareInterface && \is_string($locale) && 1 === preg_match('/^[A-Za-z]{2,4}(_[A-Za-z0-9]{2,4})*$/', $locale)) {
            $this->translator->setLocale($locale);
        }
    }
}
