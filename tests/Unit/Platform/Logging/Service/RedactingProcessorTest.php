<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform\Logging\Service;

use App\Library\Types\Model\Type;
use App\Platform\Logging\Model\RedactedFailure;
use App\Platform\Logging\Service\RedactingProcessor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RedactingProcessorTest extends TestCase
{
    private const string DETAIL = "duplicate key value\nDETAIL:  Key (email)=(a@example.org) already exists.";

    public function testADatabaseDetailLosesTheValueBareOrWrapped(): void
    {
        $record = $this->processed('Request failed', [
            'exception' => new ConflictHttpException('conflict', new RuntimeException(self::DETAIL)),
        ]);

        $exception = Type::of($record->context['exception'] ?? null)->toInstanceOf(RedactedFailure::class);
        self::assertSame(409, $exception->getStatusCode());
        self::assertStringNotContainsString('a@example.org', (string) $exception->getPrevious()?->getMessage());
        self::assertStringNotContainsString('a@example.org', $this->processed(self::DETAIL)->message);
    }

    public function testNestedContextAndExtraUrlsAndKeysAreRedacted(): void
    {
        $record = $this->processed(
            'Matched route',
            [
                'request_uri' => 'https://app.khmk-koeln.com/api/people/directory?q=Kirollos&token=abc',
                'route' => ['parameters' => ['email' => 'sara@example.org', 'firstName' => 'Sara', 'id' => 7]],
            ],
            ['url' => '/api/x?token=abc'],
        );

        self::assertSame('https://app.khmk-koeln.com/api/people/directory', $record->context['request_uri'] ?? null);

        self::assertSame(
            ['parameters' => ['email' => '[redacted]', 'firstName' => '[redacted]', 'id' => 7]],
            $record->context['route'] ?? null,
        );

        self::assertSame('/api/x', $record->extra['url'] ?? null);
    }

    public function testAFailingCommandLineIsNotLogged(): void
    {
        $record = $this->processed('Error thrown while running command "{command}".', [
            'command' => 'app:user:create sara@example.org "Sara Hanna"',
            'message' => 'Failed',
        ]);

        self::assertSame('[redacted]', $record->context['command'] ?? null);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    private function processed(string $message, array $context = [], array $extra = []): LogRecord
    {
        $record = new LogRecord(new DateTimeImmutable(), 'app', Level::Error, $message, $context, $extra);

        return new RedactingProcessor()($record);
    }
}
