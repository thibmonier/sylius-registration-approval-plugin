<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tmonier\SyliusRegistrationApprovalPlugin\DependencyInjection\Configuration;
use Tmonier\SyliusRegistrationApprovalPlugin\DependencyInjection\TmonierSyliusRegistrationApprovalExtension;

final class ConfigurationAndExtensionTest extends TestCase
{
    public function testNoChannelValidatesItsRegistrationsByDefault(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        self::assertSame([], $config['channels']);
    }

    public function testAnEmptyChannelCodeIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [['channels' => ['']]]);
    }

    public function testTheChannelsBecomeAParameterWithoutDuplicates(): void
    {
        $container = new ContainerBuilder();

        (new TmonierSyliusRegistrationApprovalExtension())->load([['channels' => ['B2B_WEB', 'B2B_WEB', 'PRO']]], $container);

        self::assertSame(['B2B_WEB', 'PRO'], $container->getParameter('tmonier_registration_approval.channels'));
    }

    public function testTheServicesOfTheContractAreDefined(): void
    {
        $container = new ContainerBuilder();
        (new TmonierSyliusRegistrationApprovalExtension())->load([], $container);

        foreach (['tmonier_registration_approval.manager', 'tmonier_registration_approval.policy', 'tmonier_registration_approval.resolver', 'tmonier_registration_approval.visitor_approval'] as $id) {
            self::assertTrue($container->hasDefinition($id), $id);
        }
        self::assertSame('tmonier_channel_access.resolver', $container->getDefinition('tmonier_registration_approval.resolver')->getDecoratedService()[0] ?? null);
    }

    public function testTheWaitingPageIsExemptedFromTheClosedChannel(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class() extends \Symfony\Component\DependencyInjection\Extension\Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'tmonier_sylius_channel_access';
            }
        });

        (new TmonierSyliusRegistrationApprovalExtension())->prepend($container);

        self::assertSame([['exempt_routes' => ['tmonier_registration_approval_shop_pending']]], $container->getExtensionConfig('tmonier_sylius_channel_access'));
    }
}
