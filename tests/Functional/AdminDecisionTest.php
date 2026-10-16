<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Functional;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Mime\Email;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;
use Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\ApprovalAwareInterface;

final class AdminDecisionTest extends WebTestCase
{
    use FunctionalTestTrait;

    /** @var array<\Symfony\Component\Mime\RawMessage> */
    private array $sent = [];

    protected function setUp(): void
    {
        $this->startScenario();
        $this->client->enableProfiler();
        $this->setMode(AccessMode::Closed);
    }

    public function testTheListShowsThePendingRegistrationsByDefault(): void
    {
        $this->account('waiting@example.com', ApprovalStatus::Pending);
        $this->account('welcome@example.com', ApprovalStatus::Approved);
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/registration-approvals');

        self::assertResponseIsSuccessful();
        $text = $crawler->filter('body')->text();
        self::assertStringContainsString('waiting@example.com', $text);
        self::assertStringNotContainsString('welcome@example.com', $text);
        self::assertCount(1, $crawler->filter('[data-test-tmonier-approve]'), 'one decision form per pending registration');
    }

    public function testApprovingSendsTheEmailAndOpensTheCatalogueAtOnce(): void
    {
        $user = $this->account('waiting@example.com', ApprovalStatus::Pending);
        $admin = $this->loginAdmin();

        $this->decide('waiting@example.com', 'approve');

        self::assertSame(ApprovalStatus::Approved, $this->statusOf('waiting@example.com'));
        $customer = $this->customerByEmail('waiting@example.com');
        self::assertInstanceOf(ApprovalAwareInterface::class, $customer);
        self::assertSame($admin->getUserIdentifier(), $customer->getApprovalDecision()?->decidedBy);
        $mail = $this->singleEmail();
        self::assertSame('waiting@example.com', $mail->getTo()[0]->getAddress());
        self::assertStringContainsString('approved', (string) $mail->getSubject());
        self::assertStringContainsString('now sign in', (string) $mail->getHtmlBody());

        $this->logout();
        $this->loginAs($user);
        $this->get($this->productUrl());
        self::assertSame(200, $this->statusCode(), 'approved: the catalogue opens without signing in again');
    }

    public function testTheEmailIsInTheDefaultLocaleOfTheChannel(): void
    {
        $this->account('waiting@example.com', ApprovalStatus::Pending);
        $channel = $this->channel();
        $channel->addLocale($this->locale('fr_FR'));
        $channel->setDefaultLocale($this->locale('fr_FR'));
        $this->entityManager()->flush();
        $this->loginAdmin();

        $this->decide('waiting@example.com', 'approve');

        $mail = $this->singleEmail();
        self::assertStringContainsString('approuvé', (string) $mail->getSubject());
        self::assertStringContainsString('Commencer mes achats', (string) $mail->getHtmlBody());
    }

    public function testRejectingKeepsTheCatalogueClosedAndTheReasonInternal(): void
    {
        $user = $this->account('waiting@example.com', ApprovalStatus::Pending);
        $this->loginAdmin();

        $this->decide('waiting@example.com', 'reject', 'Not a professional buyer');

        self::assertSame(ApprovalStatus::Rejected, $this->statusOf('waiting@example.com'));
        $customer = $this->customerByEmail('waiting@example.com');
        self::assertInstanceOf(ApprovalAwareInterface::class, $customer);
        self::assertSame('Not a professional buyer', $customer->getApprovalDecision()?->reason);
        $mail = $this->singleEmail();
        self::assertStringContainsString('not approved', (string) $mail->getHtmlBody());
        self::assertStringNotContainsString('professional buyer', (string) $mail->getHtmlBody(), 'the internal reason is not mailed');

        // No longer pending, still without access.
        $crawler = $this->client->request('GET', '/admin/registration-approvals');
        self::assertStringNotContainsString('waiting@example.com', $crawler->filter('body')->text());
        $this->logout();
        $this->loginAs($user);
        $this->get($this->productUrl());
        self::assertSame(403, $this->statusCode());
    }

    public function testADecisionIsNeverReplayed(): void
    {
        $this->account('waiting@example.com', ApprovalStatus::Pending);
        $this->loginAdmin();
        $token = $this->tokenOf('waiting@example.com', 'reject');
        $this->decide('waiting@example.com', 'approve');
        $before = $this->customerByEmail('waiting@example.com');
        self::assertInstanceOf(ApprovalAwareInterface::class, $before);
        $decidedAt = $before->getApprovalDecision()?->decidedAt;

        $crawler = $this->decide('waiting@example.com', 'reject', null, $token);

        self::assertSame(ApprovalStatus::Approved, $this->statusOf('waiting@example.com'));
        self::assertStringContainsString('already been decided', $crawler->filter('[data-test-sylius-flash-message]')->text());
        $after = $this->customerByEmail('waiting@example.com');
        self::assertInstanceOf(ApprovalAwareInterface::class, $after);
        self::assertEquals($decidedAt, $after->getApprovalDecision()?->decidedAt);
        self::assertCount(0, $this->sent, 'no second e-mail');
    }

    public function testADecisionWithoutAValidCsrfTokenIsRefused(): void
    {
        $this->account('waiting@example.com', ApprovalStatus::Pending);
        $this->loginAdmin();
        $id = $this->idOf('waiting@example.com');

        foreach ([null, 'forged', 'tmonier_registration_decision_' . ($id + 1)] as $token) {
            $this->client->request('POST', sprintf('/admin/registration-approvals/%d/approve', $id), null === $token ? [] : ['_csrf_token' => $token]);

            self::assertSame(403, $this->statusCode(), (string) $token);
        }
        self::assertSame(ApprovalStatus::Pending, $this->statusOf('waiting@example.com'));
        self::assertCount(0, $this->getMailerMessages());
    }

    public function testAnAdministratorWithoutTheAdministrationRoleIsRefused(): void
    {
        $this->account('waiting@example.com', ApprovalStatus::Pending);
        $id = $this->idOf('waiting@example.com');
        /** @var FactoryInterface<AdminUserInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.admin_user');
        $limited = $factory->createNew();
        $limited->setUsername('limited');
        $limited->setEmail('limited@example.com');
        $limited->setPlainPassword('Str0ng-Passw0rd!x');
        $limited->setEnabled(true);
        $limited->setLocaleCode('en_US');
        $limited->removeRole('ROLE_ADMINISTRATION_ACCESS');
        $limited->addRole('ROLE_API_ACCESS');
        $this->entityManager()->persist($limited);
        $this->entityManager()->flush();
        $this->client->loginUser($limited, 'admin');

        $this->client->request('POST', sprintf('/admin/registration-approvals/%d/approve', $id), ['_csrf_token' => 'whatever']);

        self::assertSame(403, $this->statusCode());
        self::assertSame(ApprovalStatus::Pending, $this->statusOf('waiting@example.com'));
    }

    public function testAnAnonymousVisitorAndACustomerCannotDecide(): void
    {
        $user = $this->account('waiting@example.com', ApprovalStatus::Pending);
        $id = $this->idOf('waiting@example.com');

        $this->client->request('POST', sprintf('/admin/registration-approvals/%d/approve', $id));
        self::assertContains($this->statusCode(), [302, 401, 403]);

        $this->loginAs($user);
        $this->client->request('POST', sprintf('/admin/registration-approvals/%d/approve', $id), ['_csrf_token' => 'x']);
        self::assertContains($this->statusCode(), [302, 401, 403]);
        $this->client->request('GET', '/admin/registration-approvals');
        self::assertContains($this->statusCode(), [302, 401, 403]);

        self::assertSame(ApprovalStatus::Pending, $this->statusOf('waiting@example.com'));
    }

    public function testOnlyPostIsAcceptedAndUnknownCustomersAre404(): void
    {
        $this->loginAdmin();
        $id = $this->idOf($this->account('waiting@example.com', ApprovalStatus::Pending)->getCustomer()?->getEmail() ?? '');

        $this->client->request('GET', sprintf('/admin/registration-approvals/%d/approve', $id));
        self::assertSame(405, $this->statusCode());

        $this->client->request('POST', '/admin/registration-approvals/999999999/approve', ['_csrf_token' => 'forged']);
        self::assertContains($this->statusCode(), [403, 404]);
        self::assertSame(ApprovalStatus::Pending, $this->statusOf('waiting@example.com'));
    }

    public function testATooLongReasonIsRefusedAndTheStatusKept(): void
    {
        $this->account('waiting@example.com', ApprovalStatus::Pending);
        $this->loginAdmin();

        $crawler = $this->decide('waiting@example.com', 'reject', str_repeat('x', 501));

        self::assertSame(ApprovalStatus::Pending, $this->statusOf('waiting@example.com'));
        self::assertStringContainsString('limited to 500', $crawler->filter('[data-test-sylius-flash-message]')->text());
    }

    /** The CSRF token of the decision form of a pending registration, read from the list like the administrator's browser does. */
    private function tokenOf(string $email, string $decision): string
    {
        $crawler = $this->client->request('GET', '/admin/registration-approvals');
        $form = $crawler->filter(sprintf('[data-test-tmonier-%s]', $decision));
        self::assertGreaterThan(0, $form->count(), $email . ' is not in the pending list');
        $row = $crawler->filter('tr')->reduce(static fn (Crawler $tr): bool => str_contains($tr->text(), $email));
        self::assertCount(1, $row);

        return (string) $row->filter(sprintf('[data-test-tmonier-%s] input[name="_csrf_token"]', $decision))->attr('value');
    }

    /** Posts the decision, keeps the e-mails it sent and follows the redirect to the list. */
    private function decide(string $email, string $decision, ?string $reason = null, ?string $token = null): Crawler
    {
        $token ??= $this->tokenOf($email, $decision);
        $this->client->request('POST', sprintf('/admin/registration-approvals/%d/%s', $this->idOf($email), $decision), ['_csrf_token' => $token, 'reason' => $reason]);
        $this->sent = $this->getMailerMessages();

        return $this->client->followRedirect();
    }

    private function locale(string $code): \Sylius\Component\Locale\Model\LocaleInterface
    {
        $locale = $this->entityManager()->getRepository(\Sylius\Component\Locale\Model\LocaleInterface::class)->findOneBy(['code' => $code]);
        if (null === $locale) {
            $factory = self::getContainer()->get('sylius.factory.locale');
            self::assertInstanceOf(FactoryInterface::class, $factory);
            $locale = $factory->createNew();
            self::assertInstanceOf(\Sylius\Component\Locale\Model\LocaleInterface::class, $locale);
            $locale->setCode($code);
            $this->entityManager()->persist($locale);
        }

        return $locale;
    }

    private function idOf(string $email): int
    {
        $id = $this->customerByEmail($email)?->getId();
        self::assertIsInt($id, $email);

        return $id;
    }

    private function singleEmail(): Email
    {
        self::assertCount(1, $this->sent);
        self::assertInstanceOf(Email::class, $this->sent[0]);

        return $this->sent[0];
    }
}
