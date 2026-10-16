<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusChannelAccessPlugin\Entity\Channel\AccessModeAwareInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalDecision;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;
use Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\ApprovalAwareInterface;
use Webmozart\Assert\Assert;

/**
 * Every test runs in a transaction rolled back at the end: the access mode of the channel is
 * changed inside it, the fixtures stay untouched.
 */
trait RegistrationApprovalTestTrait
{
    private function beginTestTransaction(): void
    {
        $this->connection()->beginTransaction();
    }

    private function rollBackTestTransaction(): void
    {
        if ($this->connection()->isTransactionActive()) {
            $this->connection()->rollBack();
        }
    }

    private function channel(): ChannelInterface&AccessModeAwareInterface
    {
        $repository = $this->entityManager()->getRepository(ChannelInterface::class);
        $channel = $repository->findOneBy(['hostname' => 'localhost']) ?? $repository->findOneBy(['enabled' => true]);
        Assert::isInstanceOf($channel, ChannelInterface::class);
        Assert::isInstanceOf($channel, AccessModeAwareInterface::class);

        return $channel;
    }

    private function channelId(): int
    {
        $id = $this->channel()->getId();
        Assert::integer($id);

        return $id;
    }

    /** A second channel on its own host, that does not validate registrations. */
    private function otherChannel(AccessMode $mode): ChannelInterface&AccessModeAwareInterface
    {
        $first = $this->channel();
        /** @var FactoryInterface<ChannelInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.channel');
        $other = $factory->createNew();
        Assert::isInstanceOf($other, AccessModeAwareInterface::class);
        $other->setCode('OTHER_WEB');
        $other->setName('Other');
        $other->setHostname('other.example');
        $other->setEnabled(true);
        $other->setTaxCalculationStrategy($first->getTaxCalculationStrategy());
        $other->setBaseCurrency($first->getBaseCurrency());
        foreach ($first->getCurrencies() as $currency) {
            $other->addCurrency($currency);
        }
        foreach ($first->getLocales() as $locale) {
            $other->addLocale($locale);
        }
        $other->setDefaultLocale($first->getDefaultLocale());
        $other->setAccessMode($mode);
        $this->entityManager()->persist($other);
        $this->entityManager()->flush();

        return $other;
    }

    private function setMode(AccessMode $mode): void
    {
        $channel = $this->channel();
        $channel->setAccessMode($mode);
        $this->entityManager()->flush();
    }

    private function localeCode(): string
    {
        return (string) $this->channel()->getDefaultLocale()?->getCode();
    }

    /** Makes a locale available in the channel (created if needed) and returns its code. */
    private function offerLocale(string $code): string
    {
        $locale = $this->entityManager()->getRepository(LocaleInterface::class)->findOneBy(['code' => $code]);
        if (null === $locale) {
            /** @var FactoryInterface<LocaleInterface> $factory */
            $factory = self::getContainer()->get('sylius.factory.locale');
            $locale = $factory->createNew();
            $locale->setCode($code);
            $this->entityManager()->persist($locale);
        }
        $this->channel()->addLocale($locale);
        $this->entityManager()->flush();

        return $code;
    }

    private function shopUrl(string $path): string
    {
        return sprintf('/%s%s', $this->localeCode(), $path);
    }

    private function product(): ProductInterface
    {
        $product = $this->entityManager()->getRepository(ProductInterface::class)->findOneBy(['enabled' => true]);
        Assert::isInstanceOf($product, ProductInterface::class);

        return $product;
    }

    private function productUrl(): string
    {
        $slug = $this->product()->getTranslation($this->localeCode())->getSlug();

        return $this->shopUrl('/products/' . $slug);
    }

    private function shopUser(): ShopUserInterface
    {
        $user = $this->entityManager()->getRepository(ShopUserInterface::class)->findOneBy(['username' => 'shop@example.com']);
        Assert::isInstanceOf($user, ShopUserInterface::class);

        return $user;
    }

    /** A customer with an enabled shop account, in the given approval state. */
    private function account(string $email, ApprovalStatus $status): ShopUserInterface
    {
        /** @var FactoryInterface<CustomerInterface> $customerFactory */
        $customerFactory = self::getContainer()->get('sylius.factory.customer');
        /** @var FactoryInterface<ShopUserInterface> $userFactory */
        $userFactory = self::getContainer()->get('sylius.factory.shop_user');
        $customer = $customerFactory->createNew();
        $customer->setEmail($email);
        $customer->setFirstName('Ada');
        $customer->setLastName('Lovelace');
        $user = $userFactory->createNew();
        $user->setPlainPassword('Str0ng-Passw0rd!x');
        $user->setEnabled(true);
        $customer->setUser($user);
        Assert::isInstanceOf($customer, ApprovalAwareInterface::class);
        if (ApprovalStatus::Pending === $status) {
            $customer->markApprovalPending();
        } else {
            $customer->decideApproval($status, new ApprovalDecision(new \DateTimeImmutable(), 'fixture', null));
        }
        $this->entityManager()->persist($customer);
        $this->entityManager()->flush();

        return $user;
    }

    private function customerByEmail(string $email): ?CustomerInterface
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(CustomerInterface::class)->findOneBy(['email' => $email]);
    }

    private function statusOf(string $email): ApprovalStatus
    {
        $customer = $this->customerByEmail($email);
        Assert::isInstanceOf($customer, ApprovalAwareInterface::class);

        return $customer->getApprovalStatus();
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }
}
