<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Mailer;

use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalPolicyInterface;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\ApprovalStatus;
use Tmonier\SyliusRegistrationApprovalPlugin\Approval\Event\ApprovalDecidedEvent;

/**
 * Tells the customer about the decision. A failing mailer is logged and never undoes or blocks the
 * decision. The mail carries no internal reason and no personal data beyond the recipient.
 */
final readonly class ApprovalMailer
{
    public const APPROVED = 'tmonier_registration_approved';

    public const REJECTED = 'tmonier_registration_rejected';

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     * @param list<string>                                  $approvalChannelCodes
     */
    public function __construct(
        private SenderInterface $sender,
        private ChannelContextInterface $channelContext,
        private ChannelRepositoryInterface $channelRepository,
        private ApprovalPolicyInterface $policy,
        private array $approvalChannelCodes,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ApprovalDecidedEvent $event): void
    {
        $email = $event->customer->getEmail();
        if (null === $email || ApprovalStatus::Pending === $event->status) {
            return;
        }

        try {
            $channel = $this->channel();
            $this->sender->send(
                ApprovalStatus::Approved === $event->status ? self::APPROVED : self::REJECTED,
                [$email],
                ['customer' => $event->customer, 'channel' => $channel, 'localeCode' => $channel?->getDefaultLocale()?->getCode() ?? 'en_US'],
            );
        } catch (\Throwable $exception) {
            $this->logger?->error('The registration decision e-mail could not be sent: {message}', ['message' => $exception->getMessage(), 'customer' => $event->customer->getId()]);
        }
    }

    private function channel(): ?ChannelInterface
    {
        try {
            $current = $this->channelContext->getChannel();
            if ($current instanceof ChannelInterface && $this->policy->requiresApproval($current)) {
                return $current;
            }
        } catch (\Throwable) {
            // No channel for this request: fall back to the first channel that validates registrations.
        }

        foreach ($this->approvalChannelCodes as $code) {
            $channel = $this->channelRepository->findOneByCode($code);
            if ($channel instanceof ChannelInterface) {
                return $channel;
            }
        }

        return null;
    }
}
