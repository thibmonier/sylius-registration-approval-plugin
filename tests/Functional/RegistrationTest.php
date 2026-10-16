<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Functional;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;

final class RegistrationTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const PASSWORD = 'Str0ng-Passw0rd!x';

    protected function setUp(): void
    {
        $this->startScenario();
        $this->setMode(AccessMode::Closed);
    }

    public function testANewRegistrationIsPendingNotSignedInAndLandsOnTheReceivedPage(): void
    {
        $email = $this->uniqueEmail();

        $page = $this->registerThroughTheForm($email);

        self::assertSame($this->shopUrl('/register/thank-you'), $page['location']);
        self::assertStringContainsString('data-test-tmonier-registration-received', $page['html']);
        self::assertSame(ApprovalStatus::Pending, $this->statusOf($email));
        // Not signed in: the account area asks for a login.
        $this->get($this->shopUrl('/account/dashboard'));
        $this->assertRedirectedToLogin();
    }

    public function testRegisteringWithAnExistingAddressLooksTheSameAndCreatesNothing(): void
    {
        $existing = $this->account($this->uniqueEmail(), ApprovalStatus::Approved);
        $email = (string) $existing->getCustomer()?->getEmail();
        $fresh = $this->registerThroughTheForm($this->uniqueEmail());
        $this->logout();

        $duplicate = $this->registerThroughTheForm($email);

        self::assertSame($fresh['location'], $duplicate['location']);
        self::assertSame(302, $duplicate['status']);
        self::assertSame($this->normalise($fresh['html']), $this->normalise($duplicate['html']));
        // No second account, the existing one is untouched, nobody is signed in.
        self::assertCount(1, $this->entityManager()->getRepository(CustomerInterface::class)->findBy(['email' => $email]));
        self::assertSame(ApprovalStatus::Approved, $this->statusOf($email));
        $this->get($this->shopUrl('/account/dashboard'));
        $this->assertRedirectedToLogin();
    }

    public function testTheOtherErrorsOfTheFormAreTheSameForAnExistingAndANewAddress(): void
    {
        $existing = $this->account($this->uniqueEmail(), ApprovalStatus::Approved);
        $taken = (string) $existing->getCustomer()?->getEmail();

        $forNew = $this->registerThroughTheForm($this->uniqueEmail(), self::PASSWORD, 'another-password');
        $forTaken = $this->registerThroughTheForm($taken, self::PASSWORD, 'another-password');

        self::assertSame(422, $forNew['status']);
        self::assertSame($forNew['status'], $forTaken['status']);
        self::assertSame($this->errorsOf($forNew['html']), $this->errorsOf($forTaken['html']));
        self::assertNotEmpty($this->errorsOf($forTaken['html']));
        self::assertStringNotContainsStringIgnoringCase('already used', $forTaken['html']);
    }

    public function testAGuestCustomerWhoRegistersLaterIsPendingToo(): void
    {
        // A customer known from a guest checkout (no account yet) gets its account through the API.
        $email = $this->uniqueEmail();
        $factory = self::getContainer()->get('sylius.factory.customer');
        self::assertInstanceOf(FactoryInterface::class, $factory);
        $customer = $factory->createNew();
        self::assertInstanceOf(CustomerInterface::class, $customer);
        $customer->setEmail($email);
        $this->entityManager()->persist($customer);
        $this->entityManager()->flush();
        self::assertSame(ApprovalStatus::Approved, $this->statusOf($email));

        $this->registerThroughTheApi($email);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(ApprovalStatus::Pending, $this->statusOf($email));
    }

    public function testTheApiRegistrationIsPendingAndDoesNotTellWhetherTheAddressIsTaken(): void
    {
        $email = $this->uniqueEmail();
        $this->registerThroughTheApi($email);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(ApprovalStatus::Pending, $this->statusOf($email));
        $firstBody = (string) $this->client->getResponse()->getContent();

        $this->registerThroughTheApi($email);

        self::assertResponseStatusCodeSame(204);
        self::assertSame($firstBody, (string) $this->client->getResponse()->getContent());
        self::assertCount(1, $this->entityManager()->getRepository(CustomerInterface::class)->findBy(['email' => $email]));
        self::assertSame(ApprovalStatus::Pending, $this->statusOf($email));
    }

    public function testTheApiStillReportsTheOtherErrors(): void
    {
        $this->registerThroughTheApi('not-an-email');

        self::assertResponseStatusCodeSame(422);
    }

    public function testAPendingApiCustomerCannotSignInToTheCatalogueButAnApprovedOneCan(): void
    {
        $this->account('pending@example.com', ApprovalStatus::Pending);
        $this->account('approved@example.com', ApprovalStatus::Approved);

        foreach (['pending@example.com' => 403, 'approved@example.com' => 200] as $email => $expected) {
            $token = $this->apiToken($email);
            $this->client->request('GET', '/api/v2/shop/products', [], [], $this->apiHeaders() + ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            self::assertSame($expected, $this->statusCode(), $email);
        }
    }

    public function testAnOpenChannelDoesNotRestrictAPendingCustomer(): void
    {
        $this->setMode(AccessMode::Open);
        $user = $this->account($this->uniqueEmail(), ApprovalStatus::Pending);
        $this->loginAs($user);

        $html = $this->get($this->productUrl());

        self::assertSame(200, $this->statusCode());
        self::assertMatchesRegularExpression($this->pricePattern(), $html);
    }

    /** @return array{status: int, location: string, html: string} */
    private function registerThroughTheForm(string $email, string $password = self::PASSWORD, string $confirmation = self::PASSWORD): array
    {
        $this->logout();
        $crawler = $this->client->request('GET', $this->shopUrl('/register'));
        $form = $crawler->filter('form[name="sylius_shop_customer_registration"]')->form();
        $form['sylius_shop_customer_registration[firstName]'] = 'Ada';
        $form['sylius_shop_customer_registration[lastName]'] = 'Lovelace';
        $form['sylius_shop_customer_registration[email]'] = $email;
        $form['sylius_shop_customer_registration[user][plainPassword][first]'] = $password;
        $form['sylius_shop_customer_registration[user][plainPassword][second]'] = $confirmation;
        $this->client->submit($form);
        $status = $this->statusCode();
        $location = $this->location();
        if ($this->client->getResponse()->isRedirect()) {
            $this->client->followRedirect();
        }

        return ['status' => $status, 'location' => $location, 'html' => (string) $this->client->getResponse()->getContent()];
    }

    private function registerThroughTheApi(string $email): void
    {
        $this->client->request('POST', '/api/v2/shop/customers', [], [], $this->apiHeaders(), (string) json_encode([
            'firstName' => 'Ada', 'lastName' => 'Lovelace', 'email' => $email, 'password' => self::PASSWORD, 'subscribedToNewsletter' => false,
        ]));
    }

    private function apiToken(string $email): string
    {
        $user = $this->customerByEmail($email)?->getUser();
        self::assertNotNull($user);
        $jwt = self::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        self::assertInstanceOf(JWTTokenManagerInterface::class, $jwt);

        return $jwt->create($user);
    }

    private function uniqueEmail(): string
    {
        return 'visitor-' . bin2hex(random_bytes(4)) . '@example.com';
    }

    /** Page text without the tokens and values that change between two requests. */
    private function normalise(string $html): string
    {
        $text = (string) preg_replace('/<(script|style)\b.*?<\/\1>/s', '', $html);
        $text = (string) preg_replace('/(value|content|nonce)="[^"]*"/', '', $text);

        return trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));
    }

    /** @return list<string> */
    private function errorsOf(string $html): array
    {
        preg_match_all('/<[^>]*invalid-feedback[^>]*>(.*?)<\/div>/s', $html, $matches);

        return array_map(static fn (string $error): string => trim((string) preg_replace('/\s+/', ' ', strip_tags($error))), $matches[1]);
    }
}
