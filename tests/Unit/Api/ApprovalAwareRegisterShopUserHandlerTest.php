<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Unit\Api;

use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ApiBundle\Command\Account\RegisterShopUser;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ShopUser;
use Sylius\Component\User\Canonicalizer\CanonicalizerInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Api\ApprovalAwareRegisterShopUserHandler;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ChannelApprovalChecker;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ConfiguredApprovalPolicy;

final class ApprovalAwareRegisterShopUserHandlerTest extends TestCase
{
    public function testAnExistingAddressOnAValidatingChannelCreatesNothing(): void
    {
        $inner = new SpyHandler();

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::once())->method('hash');

        $result = $this->handler($inner, ['B2B'], new ShopUser(), $hasher)($this->command());

        self::assertNull($result);
        self::assertSame(0, $inner->calls);
    }

    public function testANewAddressIsRegisteredByTheCoreHandler(): void
    {
        $inner = new SpyHandler();

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::never())->method('hash');

        $result = $this->handler($inner, ['B2B'], null, $hasher)($this->command());

        self::assertInstanceOf(ShopUser::class, $result);
        self::assertSame(1, $inner->calls);
    }

    public function testAChannelThatDoesNotValidateKeepsTheCoreBehaviour(): void
    {
        $inner = new SpyHandler();

        $this->handler($inner, ['OTHER'], new ShopUser())($this->command());

        self::assertSame(1, $inner->calls);
    }

    /** @param list<string> $channels */
    private function handler(SpyHandler $inner, array $channels, ?ShopUser $existing, ?PasswordHasherInterface $hasher = null): ApprovalAwareRegisterShopUserHandler
    {
        $channel = new Channel();
        $channel->setCode('B2B');
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willReturn($channel);
        $canonicalizer = $this->createStub(CanonicalizerInterface::class);
        $canonicalizer->method('canonicalize')->willReturnArgument(0);
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('findOneByEmail')->willReturn($existing);

        $factory = $this->createStub(PasswordHasherFactoryInterface::class);
        $factory->method('getPasswordHasher')->willReturn($hasher ?? $this->createStub(PasswordHasherInterface::class));

        return new ApprovalAwareRegisterShopUserHandler($inner, new ChannelApprovalChecker($context, new ConfiguredApprovalPolicy($channels)), $canonicalizer, $repository, $factory);
    }

    private function command(): RegisterShopUser
    {
        return new RegisterShopUser(channelCode: 'B2B', localeCode: 'en_US', firstName: 'Ada', lastName: 'Lovelace', email: 'ada@example.com', password: 'secret');
    }
}

final class SpyHandler
{
    public int $calls = 0;

    public function __invoke(): ShopUser
    {
        ++$this->calls;

        return new ShopUser();
    }
}
