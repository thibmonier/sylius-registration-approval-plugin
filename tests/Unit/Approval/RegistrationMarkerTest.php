<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Unit\Approval;

use PHPUnit\Framework\TestCase;
use Sylius\Bundle\AdminBundle\SectionResolver\AdminSection;
use Sylius\Bundle\ApiBundle\SectionResolver\ShopApiSection;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionInterface;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Bundle\ShopBundle\SectionResolver\ShopSection;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Customer as CoreCustomer;
use Sylius\Component\Core\Model\ShopUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tests\Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\Customer;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ConfiguredApprovalPolicy;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\RegistrationMarker;

final class RegistrationMarkerTest extends TestCase
{
    public function testARegistrationOfTheShopOnAValidatingChannelIsPending(): void
    {
        $user = $this->user();

        $this->marker(new ShopSection(), ['B2B'])->markIfRegistration($user);

        self::assertSame(ApprovalStatus::Pending, $this->customerOf($user)->getApprovalStatus());
    }

    public function testTheShopApiToo(): void
    {
        $user = $this->user();

        $this->marker(new ShopApiSection(), ['B2B'])->markIfRegistration($user);

        self::assertSame(ApprovalStatus::Pending, $this->customerOf($user)->getApprovalStatus());
    }

    public function testAnAccountCreatedInTheAdministrationStaysApproved(): void
    {
        $user = $this->user();

        $this->marker(new AdminSection(), ['B2B'])->markIfRegistration($user);

        self::assertSame(ApprovalStatus::Approved, $this->customerOf($user)->getApprovalStatus());
    }

    public function testAChannelThatDoesNotValidateKeepsTheAccountApproved(): void
    {
        $user = $this->user();

        $this->marker(new ShopSection(), ['OTHER'])->markIfRegistration($user);

        self::assertSame(ApprovalStatus::Approved, $this->customerOf($user)->getApprovalStatus());
    }

    public function testNothingChangesWithoutAnHttpRequest(): void
    {
        $user = $this->user();

        $this->marker(new ShopSection(), ['B2B'], false)->markIfRegistration($user);

        self::assertSame(ApprovalStatus::Approved, $this->customerOf($user)->getApprovalStatus());
    }

    public function testOtherEntitiesAndForeignCustomerClassesAreIgnored(): void
    {
        $marker = $this->marker(new ShopSection(), ['B2B']);
        $marker->markIfRegistration(new \stdClass());
        $marker->markIfRegistration(new Customer());

        $user = new ShopUser();
        $user->setCustomer(new CoreCustomer());
        $marker->markIfRegistration($user);

        $this->addToAssertionCount(1);
    }

    /** @param list<string> $channels */
    private function marker(SectionInterface $section, array $channels, bool $withRequest = true): RegistrationMarker
    {
        $sections = $this->createStub(SectionProviderInterface::class);
        $sections->method('getSection')->willReturn($section);
        $channel = new Channel();
        $channel->setCode('B2B');
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willReturn($channel);
        $requests = new RequestStack();
        if ($withRequest) {
            $requests->push(new Request());
        }

        return new RegistrationMarker($requests, $sections, $context, new ConfiguredApprovalPolicy($channels));
    }

    private function user(): ShopUser
    {
        $user = new ShopUser();
        $user->setUsername('a@example.com');
        $user->setCustomer(new Customer());

        return $user;
    }

    private function customerOf(ShopUser $user): Customer
    {
        $customer = $user->getCustomer();
        self::assertInstanceOf(Customer::class, $customer);

        return $customer;
    }
}
