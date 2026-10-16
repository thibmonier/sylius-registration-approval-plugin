<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tmonier\SyliusRegistrationApprovalPlugin\Access\ApprovalAwareAccessModeResolver;
use Tmonier\SyliusRegistrationApprovalPlugin\Api\ApprovalAwareRegisterShopUserHandler;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalPolicyInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ChannelApprovalChecker;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ConfiguredApprovalPolicy;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\Event\ApprovalDecidedEvent;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\RegistrationApprovalManager;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\RegistrationApprovalManagerInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\RegistrationMarker;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\VisitorApproval;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\VisitorApprovalInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Command\CheckConfigurationCommand;
use Tmonier\SyliusRegistrationApprovalPlugin\Controller\Admin\DecisionController;
use Tmonier\SyliusRegistrationApprovalPlugin\Controller\Shop\PendingController;
use Tmonier\SyliusRegistrationApprovalPlugin\EventListener\PendingCustomerListener;
use Tmonier\SyliusRegistrationApprovalPlugin\EventListener\RegistrationListener;
use Tmonier\SyliusRegistrationApprovalPlugin\EventListener\RegistrationReceivedListener;
use Tmonier\SyliusRegistrationApprovalPlugin\Form\Extension\RegistrationTypeExtension;
use Tmonier\SyliusRegistrationApprovalPlugin\Mailer\ApprovalMailer;
use Tmonier\SyliusRegistrationApprovalPlugin\Menu\AdminMenuListener;
use Tmonier\SyliusRegistrationApprovalPlugin\Shop\PendingPageRenderer;
use Tmonier\SyliusRegistrationApprovalPlugin\Validator\ApprovalAwareUniqueShopUserEmailValidator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('tmonier_registration_approval.policy', ConfiguredApprovalPolicy::class)
        ->args(['%tmonier_registration_approval.channels%'])
    ;
    $services->alias(ApprovalPolicyInterface::class, 'tmonier_registration_approval.policy');

    $services->set('tmonier_registration_approval.visitor_approval', VisitorApproval::class)
        ->args([
            service('security.token_storage'),
            service('sylius.context.channel'),
            service('request_stack'),
            service('tmonier_registration_approval.policy'),
        ])
    ;
    $services->alias(VisitorApprovalInterface::class, 'tmonier_registration_approval.visitor_approval');

    // A pending or rejected customer is anonymous for the channel access plugin.
    $services->set('tmonier_registration_approval.resolver', ApprovalAwareAccessModeResolver::class)
        ->decorate('tmonier_channel_access.resolver')
        ->args([service('.inner'), service('tmonier_registration_approval.visitor_approval')])
    ;

    $services->set('tmonier_registration_approval.manager', RegistrationApprovalManager::class)
        ->args([service('doctrine.orm.entity_manager'), service('event_dispatcher')])
    ;
    $services->alias(RegistrationApprovalManagerInterface::class, 'tmonier_registration_approval.manager');

    $services->set('tmonier_registration_approval.marker', RegistrationMarker::class)
        ->args([
            service('request_stack'),
            service('sylius.section_resolver.uri_based'),
            service('sylius.context.channel'),
            service('tmonier_registration_approval.policy'),
            service('tmonier_channel_access.resolver'),
            service('logger')->nullOnInvalid(),
        ])
    ;

    $services->set('tmonier_registration_approval.listener.registration', RegistrationListener::class)
        ->args([service('tmonier_registration_approval.marker'), service('security.token_storage'), service('request_stack'), service('router'), service('tmonier_registration_approval.channel_checker'), service('security.password_hasher_factory')])
        ->tag('kernel.event_listener', ['event' => 'sylius.customer.pre_register', 'method' => 'onPreRegister', 'priority' => 100])
        ->tag('kernel.event_listener', ['event' => 'sylius.customer.post_register', 'method' => 'onPostRegister', 'priority' => -10])
        ->tag('doctrine.event_listener', ['event' => 'prePersist'])
    ;

    $services->set('tmonier_registration_approval.shop.pending_page_renderer', PendingPageRenderer::class)
        ->args([service('twig'), '%sylius.security.api_shop_route%'])
    ;

    // After the firewall (8), before the access listener of the channel access plugin (6).
    $services->set('tmonier_registration_approval.listener.pending_customer', PendingCustomerListener::class)
        ->args([
            service('tmonier_registration_approval.visitor_approval'),
            service('tmonier_channel_access.listener.visitor_access'),
            service('tmonier_registration_approval.shop.pending_page_renderer'),
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onRequest', 'priority' => 7])
        ->tag('kernel.event_listener', ['event' => 'kernel.exception', 'method' => 'onException', 'priority' => 65])
    ;

    $services->set('tmonier_registration_approval.listener.registration_received', RegistrationReceivedListener::class)
        ->args([
            service('sylius.context.channel'),
            service('tmonier_registration_approval.policy'),
            service('tmonier_registration_approval.shop.pending_page_renderer'),
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'priority' => 4])
    ;

    $services->set('tmonier_registration_approval.mailer', ApprovalMailer::class)
        ->args([
            service('sylius.email_sender'),
            service('sylius.context.channel'),
            service('sylius.repository.channel'),
            service('tmonier_registration_approval.policy'),
            '%tmonier_registration_approval.channels%',
            service('logger')->nullOnInvalid(),
        ])
        ->tag('kernel.event_listener', ['event' => ApprovalDecidedEvent::class])
    ;

    $services->set('tmonier_registration_approval.controller.shop.pending', PendingController::class)
        ->args([
            service('tmonier_registration_approval.visitor_approval'),
            service('tmonier_registration_approval.shop.pending_page_renderer'),
            service('router'),
        ])
        ->tag('controller.service_arguments')
    ;

    $services->set('tmonier_registration_approval.controller.admin.decision', DecisionController::class)
        ->args([
            service('sylius.repository.customer'),
            service('tmonier_registration_approval.manager'),
            service('security.csrf.token_manager'),
            service('security.helper'),
            service('router'),
        ])
        ->tag('controller.service_arguments')
    ;

    $services->set('tmonier_registration_approval.channel_checker', ChannelApprovalChecker::class)
        ->args([service('sylius.context.channel'), service('tmonier_registration_approval.policy')])
    ;

    $services->set('tmonier_registration_approval.form.extension.registration', RegistrationTypeExtension::class)
        ->args([service('tmonier_registration_approval.channel_checker'), service('request_stack')])
        ->tag('form.type_extension')
    ;

    // Shop API registration: no account enumeration on the channels that validate registrations.
    $services->set('tmonier_registration_approval.validator.unique_shop_user_email', ApprovalAwareUniqueShopUserEmailValidator::class)
        ->decorate('sylius_api.validator.unique_shop_user_email')
        ->args([service('.inner'), service('tmonier_registration_approval.channel_checker')])
    ;

    $services->set('tmonier_registration_approval.api.register_shop_user_handler', ApprovalAwareRegisterShopUserHandler::class)
        ->decorate('sylius_api.command_handler.account.register_shop_user')
        ->args([
            service('.inner'),
            service('tmonier_registration_approval.channel_checker'),
            service('sylius.canonicalizer'),
            service('sylius.repository.shop_user'),
        ])
    ;

    $services->set('tmonier_registration_approval.command.check', CheckConfigurationCommand::class)
        ->args([service('sylius.repository.channel'), service('tmonier_channel_access.resolver'), '%tmonier_registration_approval.channels%'])
        ->tag('console.command')
    ;

    $services->set('tmonier_registration_approval.menu.admin', AdminMenuListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.admin.main'])
    ;
};
