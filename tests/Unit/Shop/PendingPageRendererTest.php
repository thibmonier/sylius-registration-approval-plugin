<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Unit\Shop;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;
use Tmonier\SyliusRegistrationApprovalPlugin\Shop\PendingPageRenderer;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class PendingPageRendererTest extends TestCase
{
    public function testThePageIsTranslatedInTheLocaleOfTheUrl(): void
    {
        $translator = $this->createMock(LocaleAwareInterface::class);
        $translator->expects(self::once())->method('setLocale')->with('fr_FR');
        $request = Request::create('/fr_FR/cart/');
        $request->attributes->set('_locale', 'fr_FR');

        $response = $this->renderer($translator)->render($request, ApprovalStatus::Pending);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('page pending', $response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testAForgedLocaleIsIgnored(): void
    {
        $translator = $this->createMock(LocaleAwareInterface::class);
        $translator->expects(self::never())->method('setLocale');
        $request = Request::create('/x');
        $request->attributes->set('_locale', '../../etc');

        $this->renderer($translator)->renderReceived($request);
    }

    public function testTheShopApiGetsAJson403(): void
    {
        $response = $this->renderer()->render(Request::create('/api/v2/shop/products'), ApprovalStatus::Rejected);

        self::assertSame(403, $response->getStatusCode());
        self::assertJson((string) $response->getContent());
        self::assertStringContainsString('not approved', (string) $response->getContent());
    }

    public function testTheWaitingPageItselfIsA200(): void
    {
        self::assertSame(200, $this->renderer()->render(Request::create('/fr_FR/registration/pending'), ApprovalStatus::Pending, 200)->getStatusCode());
    }

    private function renderer(?LocaleAwareInterface $translator = null): PendingPageRenderer
    {
        $twig = new Environment(new ArrayLoader([
            '@TmonierSyliusRegistrationApprovalPlugin/shop/pending.html.twig' => 'page {{ status }}',
            '@TmonierSyliusRegistrationApprovalPlugin/shop/received.html.twig' => 'received',
        ]));

        return new PendingPageRenderer($twig, '/api/v2/shop', $translator);
    }
}
