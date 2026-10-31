<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Assert;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class Logs
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @return list<string>
     */
    public function containing(string $needle): array
    {
        $formatter = new NormalizerFormatter();
        $encode = static fn (LogRecord $record): string => json_encode(
            $formatter->format($record),
            JSON_THROW_ON_ERROR,
        );
        $lines = array_map($encode, $this->handler()->getRecords());

        return array_values(array_filter($lines, static fn (string $line): bool => str_contains($line, $needle)));
    }

    /**
     * @return list<LogRecord>
     */
    public function atOrAbove(Level $level): array
    {
        return array_values(array_filter(
            $this->handler()->getRecords(),
            static fn (LogRecord $record): bool => $record->level->value >= $level->value,
        ));
    }

    private function handler(): TestHandler
    {
        $handler = $this->container->get('monolog.handler.test');
        Assert::assertInstanceOf(TestHandler::class, $handler);

        return $handler;
    }
}
