<?php

declare(strict_types=1);

namespace App\Platform\Mail\Service;

use App\Platform\Mail\Contract\MailQueue;
use ArrayObject;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsEventListener(event: KernelEvents::TERMINATE, method: 'sendAll')]
#[AsEventListener(event: ConsoleEvents::TERMINATE, method: 'sendAll')]
final readonly class DeferredMailQueue implements MailQueue
{
    /**
     * @var ArrayObject<string, Email>
     */
    private ArrayObject $pending;

    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
    ) {
        $this->pending = new ArrayObject();
    }

    #[Override]
    public function queue(Email $email, string $label): void
    {
        $this->pending->offsetSet($label . '#' . $this->pending->count(), $email);
    }

    public function sendAll(): void
    {
        $pending = $this->pending->getArrayCopy();
        $this->pending->exchangeArray([]);

        foreach ($pending as $label => $email) {
            $this->send($label, $email);
        }
    }

    private function send(string $label, Email $email): void
    {
        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface) {
            $this->logger->error('Sending an email failed.', ['mail' => $label]);
        }
    }
}
