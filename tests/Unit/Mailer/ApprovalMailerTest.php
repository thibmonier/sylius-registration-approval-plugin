<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Unit\Mailer;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ConfiguredApprovalPolicy;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\Event\ApprovalDecidedEvent;
use Tmonier\SyliusRegistrationApprovalPlugin\Mailer\ApprovalMailer;

final class ApprovalMailerTest extends TestCase
{
    public function testApprovalAndRejectionUseTheirOwnTemplate(): void
    {
        foreach ([[ApprovalStatus::Approved, 'tmonier_registration_approved'], [ApprovalStatus::Rejected, 'tmonier_registration_rejected']] as [$status, $code]) {
            $sender = $this->createMock(SenderInterface::class);
            $sender->expects(self::once())->method('send')->with($code, ['ada@example.com'], self::callback(static fn (array $data): bool => 'fr_FR' === $data['localeCode'] && !isset($data['reason'])));

            $this->mailer($sender)(new ApprovalDecidedEvent($this->customer(), $status, 'sophie', 'internal note'));
        }
    }

    public function testAFailingMailerIsLoggedAndNeverThrows(): void
    {
        $sender = $this->createStub(SenderInterface::class);
        $sender->method('send')->willThrowException(new \RuntimeException('SMTP down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $this->mailer($sender, $logger)(new ApprovalDecidedEvent($this->customer(), ApprovalStatus::Approved, 'sophie', null));
    }

    public function testNothingIsSentForAPendingStatusOrWithoutAnAddress(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::never())->method('send');
        $mailer = $this->mailer($sender);

        $mailer(new ApprovalDecidedEvent($this->customer(), ApprovalStatus::Pending, 'sophie', null));
        $mailer(new ApprovalDecidedEvent(new Customer(), ApprovalStatus::Approved, 'sophie', null));
    }

    private function mailer(SenderInterface $sender, ?LoggerInterface $logger = null): ApprovalMailer
    {
        $locale = new Locale();
        $locale->setCode('fr_FR');
        $channel = new Channel();
        $channel->setCode('B2B');
        $channel->setDefaultLocale($locale);
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willThrowException(new ChannelNotFoundException());
        $repository = $this->createStub(ChannelRepositoryInterface::class);
        $repository->method('findOneByCode')->willReturn($channel);

        return new ApprovalMailer($sender, $context, $repository, new ConfiguredApprovalPolicy(['B2B']), ['B2B'], $logger);
    }

    private function customer(): Customer
    {
        $customer = new Customer();
        $customer->setEmail('ada@example.com');

        return $customer;
    }
}
