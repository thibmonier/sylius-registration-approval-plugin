<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Customer as BaseCustomer;
use Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\ApprovalAwareInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\ApprovalTrait;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_customer')]
class Customer extends BaseCustomer implements ApprovalAwareInterface
{
    use ApprovalTrait;
}
