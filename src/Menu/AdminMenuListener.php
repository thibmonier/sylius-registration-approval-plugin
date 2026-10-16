<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Menu;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $customers = $event->getMenu()->getChild('customers');
        if (!$customers instanceof ItemInterface) {
            return;
        }

        $customers
            ->addChild('tmonier_registration_approvals', ['route' => 'tmonier_registration_approval_admin_index'])
            ->setLabel('tmonier_registration_approval.ui.registrations')
            ->setLabelAttribute('icon', 'tabler:user-check')
        ;
    }
}
