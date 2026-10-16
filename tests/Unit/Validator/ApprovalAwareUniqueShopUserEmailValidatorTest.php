<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Unit\Validator;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\Channel;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ChannelApprovalChecker;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ConfiguredApprovalPolicy;
use Tmonier\SyliusRegistrationApprovalPlugin\Validator\ApprovalAwareUniqueShopUserEmailValidator;

final class ApprovalAwareUniqueShopUserEmailValidatorTest extends TestCase
{
    public function testItDoesNotCheckOnAValidatingChannel(): void
    {
        $inner = $this->createMock(ConstraintValidatorInterface::class);
        $inner->expects(self::never())->method('validate');

        $this->validator($inner, ['B2B'])->validate('ada@example.com', $this->createStub(Constraint::class));
    }

    public function testItChecksOnAnyOtherChannel(): void
    {
        $inner = $this->createMock(ConstraintValidatorInterface::class);
        $inner->expects(self::once())->method('validate');

        $this->validator($inner, ['OTHER'])->validate('ada@example.com', $this->createStub(Constraint::class));
    }

    public function testTheContextReachesTheDecoratedValidator(): void
    {
        $context = $this->createStub(ExecutionContextInterface::class);
        $inner = $this->createMock(ConstraintValidatorInterface::class);
        $inner->expects(self::once())->method('initialize')->with($context);

        $this->validator($inner, [])->initialize($context);
    }

    /** @param list<string> $channels */
    private function validator(ConstraintValidatorInterface $inner, array $channels): ApprovalAwareUniqueShopUserEmailValidator
    {
        $channel = new Channel();
        $channel->setCode('B2B');
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willReturn($channel);

        return new ApprovalAwareUniqueShopUserEmailValidator($inner, new ChannelApprovalChecker($context, new ConfiguredApprovalPolicy($channels)));
    }
}
