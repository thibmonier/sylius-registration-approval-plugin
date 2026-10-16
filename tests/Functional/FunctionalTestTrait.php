<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Functional;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Tests\Tmonier\SyliusRegistrationApprovalPlugin\RegistrationApprovalTestTrait;

trait FunctionalTestTrait
{
    use RegistrationApprovalTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->startScenario();
    }

    private function startScenario(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    private function loginCustomer(): void
    {
        $this->client->loginUser($this->shopUser(), 'shop');
    }

    private function loginAs(ShopUserInterface $user): void
    {
        $this->client->loginUser($user, 'shop');
    }

    private function loginAdmin(): AdminUserInterface
    {
        $admin = $this->entityManager()->getRepository(AdminUserInterface::class)->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        $this->client->loginUser($admin, 'admin');

        return $admin;
    }

    private function logout(): void
    {
        $this->client->getCookieJar()->clear();
        $this->client->restart();
        $this->client->disableReboot();
    }

    private function get(string $url): string
    {
        $this->client->request('GET', $url);

        return (string) $this->client->getResponse()->getContent();
    }

    private function statusCode(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }

    private function location(): string
    {
        return (string) $this->client->getResponse()->headers->get('Location');
    }

    private function assertRedirectedToLogin(string $message = ''): void
    {
        self::assertSame(302, $this->statusCode(), $message);
        self::assertSame($this->shopUrl('/login'), parse_url($this->location(), \PHP_URL_PATH), $message);
    }

    /** A price as the shop formats it in en_US ("$12.34"). */
    private function pricePattern(): string
    {
        return '/\$\d[\d,]*\.\d\d/';
    }

    /** @param list<string> $urls */
    private function assertNoPriceIn(array $urls): void
    {
        foreach ($urls as $url) {
            $html = $this->get($url);
            self::assertSame(200, $this->statusCode(), $url);
            self::assertDoesNotMatchRegularExpression($this->pricePattern(), $html, $url);
        }
    }

    /** @return array<string, string> */
    private function apiHeaders(bool $signedIn = false): array
    {
        $headers = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
        if ($signedIn) {
            $jwt = self::getContainer()->get('lexik_jwt_authentication.jwt_manager');
            self::assertInstanceOf(JWTTokenManagerInterface::class, $jwt);
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $jwt->create($this->shopUser());
        }

        return $headers;
    }
}
