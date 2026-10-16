<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Command;

use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;

/**
 * A channel that validates registrations but stays `open` restricts nobody: the pending customers
 * browse and buy as before. This diagnostic (also useful in CI) lists the problems.
 */
#[AsCommand(name: 'tmonier:registration-approval:check', description: 'Checks that the channels that validate registrations really restrict pending customers')]
final class CheckConfigurationCommand extends Command
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     * @param list<string>                                  $channelCodes
     */
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly AccessModeResolverInterface $accessModes,
        private readonly array $channelCodes,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $problems = 0;

        foreach ($this->channelCodes as $code) {
            $channel = $this->channelRepository->findOneByCode($code);
            if (!$channel instanceof ChannelInterface) {
                $io->error(sprintf('The channel "%s" does not exist.', $code));
                ++$problems;

                continue;
            }

            if (AccessMode::Open === $this->accessModes->modeOf($channel)) {
                $io->warning(sprintf('The channel "%s" validates registrations but its access mode is "open": pending customers are not restricted. Set it to "prices_hidden" or "closed".', $code));
                ++$problems;

                continue;
            }

            $io->success(sprintf('The channel "%s" validates registrations (access mode "%s").', $code, $this->accessModes->modeOf($channel)->value));
        }

        if ([] === $this->channelCodes) {
            $io->note('No channel validates registrations (tmonier_sylius_registration_approval.channels is empty).');
        }

        return $problems > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
