<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Api;

use Sylius\Bundle\ApiBundle\Command\Account\RegisterShopUser;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\User\Canonicalizer\CanonicalizerInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ChannelApprovalChecker;

/**
 * Decorates the registration handler of the shop API. On a channel that validates registrations
 * an address that already has an account creates nothing and answers like a new registration
 * (204): the API does not tell whether an account exists.
 */
final readonly class ApprovalAwareRegisterShopUserHandler
{
    private \Closure $inner;

    /**
     * @param callable(RegisterShopUser): mixed       $inner              the decorated handler
     * @param UserRepositoryInterface<ShopUserInterface> $shopUserRepository
     */
    public function __construct(
        callable $inner,
        private ChannelApprovalChecker $channelChecker,
        private CanonicalizerInterface $canonicalizer,
        private UserRepositoryInterface $shopUserRepository,
        private PasswordHasherFactoryInterface $hasherFactory,
    ) {
        $this->inner = $inner(...);
    }

    public function __invoke(RegisterShopUser $command): ?ShopUserInterface
    {
        if ($this->channelChecker->currentChannelRequiresApproval() && $this->hasAccount($command->email)) {
            // A real registration hashes the password: do the same work so the time does not tell.
            $this->hasherFactory->getPasswordHasher(ShopUserInterface::class)->hash(bin2hex(random_bytes(16)));

            return null;
        }

        $user = ($this->inner)($command);

        return $user instanceof ShopUserInterface ? $user : null;
    }

    private function hasAccount(string $email): bool
    {
        $canonical = $this->canonicalizer->canonicalize($email);

        return null !== $canonical && $this->shopUserRepository->findOneByEmail($canonical) instanceof \Sylius\Component\User\Model\UserInterface;
    }
}
