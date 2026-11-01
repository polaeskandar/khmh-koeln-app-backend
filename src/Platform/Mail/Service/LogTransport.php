<?php

declare(strict_types=1);

namespace App\Platform\Mail\Service;

use App\Library\Strings\Model\Text;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class LogTransport extends AbstractTransport
{
    public function __construct(
        private readonly LoggerInterface $mailLogger,
        private readonly string $name,
        ?EventDispatcherInterface $events,
    ) {
        parent::__construct($events);
    }

    #[Override]
    public function __toString(): string
    {
        return 'log://' . $this->name;
    }

    #[Override]
    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        $this->mailLogger->info('Email not sent (development).', [
            'email' => $original instanceof Email ? self::summaryOf($original) : $original->toString(),
        ]);
    }

    private static function summaryOf(Email $email): string
    {
        $recipient = new ArrayCollection($email->getTo())->first();

        return Text::format(
            "To: %s\nSubject: %s\n\n%s",
            $recipient instanceof Address ? $recipient->toString() : '',
            $email->getSubject() ?? '',
            Type::of($email->getTextBody())->toStringOrNull() ?? '',
        )->toString();
    }
}
