<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api\Auth;

use PHPUnit\Framework\Assert;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Mime\Email;

final readonly class EmailedLinks
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function lastToken(): string
    {
        $listener = $this->container->get('mailer.message_logger_listener');
        Assert::assertInstanceOf(MessageLoggerListener::class, $listener);
        $messages = $listener->getEvents()->getMessages();
        $email = end($messages);
        Assert::assertInstanceOf(Email::class, $email);
        $matches = [];
        Assert::assertSame(1, preg_match('/#token=([A-Za-z0-9_-]{43})/', (string) $email->getTextBody(), $matches));

        return $matches[1] ?? '';
    }
}
