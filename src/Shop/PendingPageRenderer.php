<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Shop;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;
use Twig\Environment;

/** The waiting page, and the same refusal for the shop API. */
final readonly class PendingPageRenderer
{
    public function __construct(
        private Environment $twig,
        private string $apiPathPrefix,
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

        $response = new Response($this->twig->render('@TmonierSyliusRegistrationApprovalPlugin/shop/pending.html.twig', ['status' => $status->value]), $httpStatus);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function renderReceived(): Response
    {
        $response = new Response($this->twig->render('@TmonierSyliusRegistrationApprovalPlugin/shop/received.html.twig'));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
