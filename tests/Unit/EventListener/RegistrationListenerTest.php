<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Unit\EventListener;

use PHPUnit\Framework\TestCase;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Bundle\ShopBundle\SectionResolver\ShopSection;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Tests\Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\Customer;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ConfiguredApprovalPolicy;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\RegistrationMarker;
use Tmonier\SyliusRegistrationApprovalPlugin\EventListener\RegistrationListener;
use Tmonier\SyliusRegistrationApprovalPlugin\Form\Extension\RegistrationTypeExtension;

final class RegistrationListenerTest extends TestCase
{
    public function testAnExistingAddressStopsTheCreationAndAnswersLikeASuccess(): void
    {
        $request = new Request();
        $request->attributes->set(RegistrationTypeExtension::DUPLICATE_EMAIL_ATTRIBUTE, true);
        $event = new ResourceControllerEvent(new Customer());

        $this->listener($request)->onPreRegister($event);

        self::assertTrue($event->isStopped());
        self::assertSame('sylius.customer.register', $event->getMessage());
        self::assertSame(ResourceControllerEvent::TYPE_SUCCESS, $event->getMessageType());
        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/en_US/register/thank-you', $response->getTargetUrl());
    }

    public function testANewAddressIsLeftToTheRegistration(): void
    {
        $event = new ResourceControllerEvent(new Customer());

        $this->listener(new Request())->onPreRegister($event);

        self::assertFalse($event->isStopped());
        self::assertNull($event->getResponse());
    }

    public function testAPendingNewcomerIsSignedOutAndSentToTheReceivedPage(): void
    {
        $storage = new TokenStorage();
        $storage->setToken(new NullToken());
        $customer = new Customer();
        $customer->markApprovalPending();
        $event = new ResourceControllerEvent($customer);

        $this->listener(new Request(), $storage)->onPostRegister($event);

        self::assertNull($storage->getToken());
        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }

    public function testAnApprovedCustomerStaysSignedIn(): void
    {
        $storage = new TokenStorage();
        $storage->setToken(new NullToken());
        $event = new ResourceControllerEvent(new Customer());

        $this->listener(new Request(), $storage)->onPostRegister($event);

        self::assertNotNull($storage->getToken());
        self::assertNull($event->getResponse());
    }

    private function listener(Request $request, ?TokenStorage $storage = null): RegistrationListener
    {
        $requests = new RequestStack();
        $requests->push($request);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/en_US/register/thank-you');
        $sections = $this->createStub(SectionProviderInterface::class);
        $sections->method('getSection')->willReturn(new ShopSection());
        $marker = new RegistrationMarker($requests, $sections, $this->createStub(ChannelContextInterface::class), new ConfiguredApprovalPolicy([]));

        return new RegistrationListener($marker, $storage ?? new TokenStorage(), $requests, $urls);
    }
}
