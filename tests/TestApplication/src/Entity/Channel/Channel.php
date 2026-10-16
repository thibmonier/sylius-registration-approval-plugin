<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Entity\Channel;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Channel as BaseChannel;
use Tmonier\SyliusChannelAccessPlugin\Entity\Channel\AccessModeAwareInterface;
use Tmonier\SyliusChannelAccessPlugin\Entity\Channel\AccessModeTrait;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_channel')]
class Channel extends BaseChannel implements AccessModeAwareInterface
{
    use AccessModeTrait;
}
