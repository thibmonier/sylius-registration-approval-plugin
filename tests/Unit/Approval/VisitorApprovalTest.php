<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Unit\Approval;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Customer as CoreCustomer;
use Sylius\Component\Core\Model\ShopUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Tests\Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\Customer;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ConfiguredApprovalPolicy;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\VisitorApproval;

final class VisitorApprovalTest extends TestCase
{
    /** @return iterable<string, array{ApprovalStatus, list<string>, ?ApprovalStatus}> */
    public static function matrix(): iterable
    {
        yield 'pending on a validating channel' => [ApprovalStatus::Pending, ['B2B'], ApprovalStatus::Pending];
        yield 'rejected on a validating channel' => [ApprovalStatus::Rejected, ['B2B'], ApprovalStatus::Rejected];
        yield 'approved' => [ApprovalStatus::Approved, ['B2B'], null];
        yield 'pending on a channel that does not validate' => [ApprovalStatus::Pending, ['OTHER'], null];
        yield 'pending, no channel validates' => [ApprovalStatus::Pending, [], null];
    }

    /** @param list<string> $channels */
    #[DataProvider('matrix')]
    public function testRestrictedStatus(ApprovalStatus $status, array $channels, ?ApprovalStatus $expected): void
    {
        $customer = new Customer();
        $customer->setEmail('a@example.com');
        ApprovalStatus::Pending === $status ? $customer->markApprovalPending() : $customer->decideApproval($status, new \Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalDecision(new \DateTimeImmutable(), 'x', null));

        self::assertSame($expected, $this->approval($customer, $channels)->restrictedStatus());
    }

    public function testAnAnonymousVisitorIsNotRestricted(): void
    {
        self::assertNull($this->approval(null, ['B2B'])->restrictedStatus());
    }

    public function testACustomerClassWithoutTheTraitIsNotRestricted(): void
    {
        self::assertNull($this->approval(new CoreCustomer(), ['B2B'])->restrictedStatus());
    }

    public function testNoHttpRequestMeansNoRestriction(): void
    {
        $customer = new Customer();
        $customer->markApprovalPending();

        self::assertNull($this->approval($customer, ['B2B'], false)->restrictedStatus());
    }

    public function testNoChannelMeansNoRestriction(): void
    {
        $customer = new Customer();
        $customer->markApprovalPending();
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willThrowException(new ChannelNotFoundException());

        self::assertNull((new VisitorApproval($this->storage($customer), $context, $this->requests(true), new ConfiguredApprovalPolicy(['B2B'])))->restrictedStatus());
    }

    /** @param list<string> $channels */
    private function approval(?object $customer, array $channels, bool $withRequest = true): VisitorApproval
    {
        $channel = new Channel();
        $channel->setCode('B2B');
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willReturn($channel);

        return new VisitorApproval($this->storage($customer), $context, $this->requests($withRequest), new ConfiguredApprovalPolicy($channels));
    }

    private function storage(?object $customer): TokenStorage
    {
        $storage = new TokenStorage();
        if ($customer instanceof CoreCustomer) {
            $user = new ShopUser();
            $user->setUsername('a@example.com');
            $user->setCustomer($customer);
            $storage->setToken(new UsernamePasswordToken($user, 'shop', ['ROLE_USER']));
        }

        return $storage;
    }

    private function requests(bool $withRequest): RequestStack
    {
        $stack = new RequestStack();
        if ($withRequest) {
            $stack->push(new Request());
        }

        return $stack;
    }
}
