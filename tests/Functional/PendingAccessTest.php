<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Functional;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;

final class PendingAccessTest extends WebTestCase
{
    use FunctionalTestTrait;

    protected function setUp(): void
    {
        $this->startScenario();
    }

    /** @return iterable<string, array{ApprovalStatus}> */
    public static function restrictedStatuses(): iterable
    {
        yield 'pending' => [ApprovalStatus::Pending];
        yield 'rejected' => [ApprovalStatus::Rejected];
    }

    #[DataProvider('restrictedStatuses')]
    public function testOnAClosedChannelTheCatalogueAnswers403WithTheWaitingPage(ApprovalStatus $status): void
    {
        $this->setMode(AccessMode::Closed);
        $this->loginAs($this->account('wait-' . $status->value . '@example.com', $status));

        foreach ([$this->productUrl(), $this->shopUrl('/taxons/t-shirts'), $this->shopUrl('/cart/'), $this->shopUrl('/products/no-such-product'), $this->shopUrl('/account/dashboard')] as $url) {
            $html = $this->get($url);

            self::assertSame(403, $this->statusCode(), $url);
            self::assertStringContainsString('data-test-tmonier-pending', $html, $url);
            self::assertStringContainsString('data-status="' . $status->value . '"', $html, $url);
            self::assertStringNotContainsString((string) $this->product()->getName(), $html, $url);
            self::assertDoesNotMatchRegularExpression($this->pricePattern(), $html, $url);
        }
    }

    #[DataProvider('restrictedStatuses')]
    public function testTheShopApiAnswers403(ApprovalStatus $status): void
    {
        $this->setMode(AccessMode::Closed);
        $user = $this->account('api-' . $status->value . '@example.com', $status);

        $this->client->request('GET', '/api/v2/shop/products', [], [], $this->apiHeaders() + ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($user)]);

        self::assertSame(403, $this->statusCode());
        self::assertStringNotContainsString('hydra:member', (string) $this->client->getResponse()->getContent());
    }

    public function testThePagesLeftOpenByTheClosedChannelStayReachable(): void
    {
        $this->setMode(AccessMode::Closed);
        $this->loginAs($this->account('wait@example.com', ApprovalStatus::Pending));

        foreach ([$this->shopUrl('/'), $this->shopUrl('/registration/pending')] as $url) {
            $this->get($url);
            self::assertSame(200, $this->statusCode(), $url);
        }
        $html = $this->get($this->shopUrl('/'));
        self::assertDoesNotMatchRegularExpression($this->pricePattern(), $html, 'no price on the home page');
    }

    public function testThePendingPageSaysWhereTheCustomerStands(): void
    {
        $this->setMode(AccessMode::Closed);
        $this->loginAs($this->account('wait@example.com', ApprovalStatus::Pending));

        $html = $this->get($this->shopUrl('/registration/pending'));

        self::assertSame(200, $this->statusCode());
        self::assertStringContainsString('awaiting approval', $html);
    }

    public function testAnonymousAndApprovedVisitorsAreNotShownTheWaitingPage(): void
    {
        $this->setMode(AccessMode::Closed);
        $this->get($this->shopUrl('/registration/pending'));
        self::assertSame(302, $this->statusCode());

        $this->loginAs($this->account('ok@example.com', ApprovalStatus::Approved));
        $this->get($this->shopUrl('/registration/pending'));
        self::assertSame(302, $this->statusCode());
        $html = $this->get($this->productUrl());
        self::assertSame(200, $this->statusCode());
        self::assertMatchesRegularExpression($this->pricePattern(), $html);
    }

    #[DataProvider('restrictedStatuses')]
    public function testOnAPricesHiddenChannelTheyBrowseWithoutPricesAndCannotBuy(ApprovalStatus $status): void
    {
        $this->setMode(AccessMode::PricesHidden);
        $this->loginAs($this->account('browse-' . $status->value . '@example.com', $status));

        $html = $this->get($this->productUrl());

        self::assertSame(200, $this->statusCode());
        self::assertDoesNotMatchRegularExpression($this->pricePattern(), $html);
        self::assertStringContainsString('data-test-tmonier-prices-hidden', $html);
        self::assertStringNotContainsString('add_to_cart_form', $html);

        $this->get($this->shopUrl('/cart/'));
        self::assertSame(403, $this->statusCode());
    }

    public function testAnApprovedCustomerHasEverything(): void
    {
        foreach ([AccessMode::Closed, AccessMode::PricesHidden] as $mode) {
            $this->setMode($mode);
            $this->loginAs($this->account('full-' . $mode->value . '@example.com', ApprovalStatus::Approved));

            $html = $this->get($this->productUrl());

            self::assertSame(200, $this->statusCode(), $mode->value);
            self::assertMatchesRegularExpression($this->pricePattern(), $html, $mode->value);
            self::assertStringContainsString('add_to_cart_form', $html, $mode->value);
        }
    }

    private function token(\Sylius\Component\Core\Model\ShopUserInterface $user): string
    {
        $jwt = self::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        self::assertInstanceOf(JWTTokenManagerInterface::class, $jwt);

        return $jwt->create($user);
    }
}
