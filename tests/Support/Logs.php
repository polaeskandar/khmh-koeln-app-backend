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
        return array_values(array_filter($this->lines(), static fn (string $line): bool => str_contains(
            $line,
            $needle,
        )));
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        $formatter = new NormalizerFormatter();

        $encode = static fn (LogRecord $record): string => json_encode(
            $formatter->format($record),
            JSON_THROW_ON_ERROR,
        );

        return array_values(array_map($encode, $this->handler()->getRecords()));
    }

    public function writesInsideLock(string $lockedTable, string ...$writes): bool
    {
        $lines = $this->lines();

        $at =
            static fn (string $needle): array => array_keys(array_filter($lines, static fn (string $line): bool => str_contains(
                $line,
                $needle,
            )));

        $lock = array_values(array_intersect($at('FOR UPDATE'), $at($lockedTable)))[0] ?? -1;
        $written = array_map($at, $writes);
        $indexes = array_merge(...$written);
        $later = array_filter($at('Committing transaction'), static fn (int $index): bool => $index > $lock);
        $commit = array_values($later)[0] ?? -1;
        $everyWrite = !in_array([], $written, strict: true);

        return $lock >= 0 && $everyWrite && $indexes !== [] && $lock < min($indexes) && max($indexes) < $commit;
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
