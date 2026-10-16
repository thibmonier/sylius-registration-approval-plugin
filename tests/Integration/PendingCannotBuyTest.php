<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Integration;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tests\Tmonier\SyliusRegistrationApprovalPlugin\RegistrationApprovalTestTrait;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;

/** The access plugin's order modifier guard sees a pending or rejected customer as anonymous. */
final class PendingCannotBuyTest extends KernelTestCase
{
    use RegistrationApprovalTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    public function testAPendingOrRejectedCustomerCannotAddToACartOnAPricesHiddenChannel(): void
    {
        $this->setMode(AccessMode::PricesHidden);

        foreach ([ApprovalStatus::Pending, ApprovalStatus::Rejected] as $status) {
            $this->signIn('buy-' . $status->value . '@example.com', $status);
            [$cart, $item] = $this->cartAndItem();

            try {
                $this->modifier()->addToOrder($cart, $item);
                self::fail($status->value . ' customers must not buy');
            } catch (AccessDeniedException) {
                self::assertCount(0, $cart->getItems());
            }
        }
    }

    public function testAnApprovedCustomerCanAndTheChannelDecidesWhenOpen(): void
    {
        $this->setMode(AccessMode::PricesHidden);
        $this->signIn('buy-ok@example.com', ApprovalStatus::Approved);
        [$cart, $item] = $this->cartAndItem();
        self::getContainer()->get(\Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface::class);
        $this->modifier()->addToOrder($cart, $item);
        self::assertCount(1, $cart->getItems());

        $this->setMode(AccessMode::Open);
        $this->signIn('buy-open@example.com', ApprovalStatus::Pending);
        [$openCart, $openItem] = $this->cartAndItem();
        $this->modifier()->addToOrder($openCart, $openItem);
        self::assertCount(1, $openCart->getItems(), 'an open channel restricts nobody');
    }

    private function signIn(string $email, ApprovalStatus $status): void
    {
        $storage = self::getContainer()->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $storage);
        // The account is created outside of any HTTP request (a request would mark it pending).
        $user = $this->account($email, $status);
        $requests = self::getContainer()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requests);
        if (!$requests->getMainRequest() instanceof \Symfony\Component\HttpFoundation\Request) {
            $requests->push(Request::create('http://localhost/en_US/'));
        }
        $storage->setToken(new UsernamePasswordToken($user, 'shop', $user->getRoles()));
    }

    private function modifier(): OrderModifierInterface
    {
        $modifier = self::getContainer()->get('sylius.modifier.order');
        self::assertInstanceOf(OrderModifierInterface::class, $modifier);

        return $modifier;
    }

    /** @return array{OrderInterface, OrderItemInterface} */
    private function cartAndItem(): array
    {
        /** @var FactoryInterface<OrderInterface> $orderFactory */
        $orderFactory = self::getContainer()->get('sylius.factory.order');
        /** @var FactoryInterface<OrderItemInterface> $itemFactory */
        $itemFactory = self::getContainer()->get('sylius.factory.order_item');
        $cart = $orderFactory->createNew();
        $cart->setChannel($this->channel());
        $cart->setCurrencyCode((string) $this->channel()->getBaseCurrency()?->getCode());
        $cart->setLocaleCode($this->localeCode());
        $item = $itemFactory->createNew();
        $product = $this->entityManager()->getRepository(ProductInterface::class)->findOneBy(['enabled' => true]);
        self::assertInstanceOf(ProductInterface::class, $product);
        $variant = $product->getEnabledVariants()->first();
        self::assertInstanceOf(ProductVariantInterface::class, $variant);
        $item->setVariant($variant);
        $item->setUnitPrice(1000);
        $quantityModifier = self::getContainer()->get('sylius.modifier.order_item_quantity');
        self::assertInstanceOf(OrderItemQuantityModifierInterface::class, $quantityModifier);
        $quantityModifier->modify($item, 1);

        return [$cart, $item];
    }
}
