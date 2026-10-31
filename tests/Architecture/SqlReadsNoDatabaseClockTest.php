<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class SqlReadsNoDatabaseClockTest extends TestCase
{
    private const string DATABASE_CLOCK = '/\b(CURRENT_DATE|CURRENT_TIMESTAMP|CURRENT_TIME|LOCALTIMESTAMP)\b|::\s*date\b|\bnow\s*\(/i';

    private const array STRING_TOKENS = [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE];

    public function testNoStringInSrcAsksTheDatabaseForTheTime(): void
    {
        $offending = array_filter(
            iterator_to_array(self::stringsInSrc(), preserve_keys: false),
            self::readsTheDatabaseClock(...),
        );

        self::assertSame(
            [],
            array_values($offending),
            'Take today and now from the Calendar and pass them as parameters.',
        );
    }

    #[DataProvider('databaseClocks')]
    public function testEveryWayToAskTheDatabaseForTheTimeIsRecognised(string $sql): void
    {
        self::assertTrue(self::readsTheDatabaseClock($sql));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function databaseClocks(): iterable
    {
        yield 'current date' => ['SELECT * FROM meeting WHERE day = current_date'];
        yield 'date cast' => ['WHERE starts_at::date = :day'];
        yield 'now' => ['WHERE starts_at < NOW()'];
        yield 'current timestamp' => ['DEFAULT CURRENT_TIMESTAMP'];
        yield 'local timestamp' => ['SELECT LOCALTIMESTAMP'];
        yield 'current time' => ['SELECT CURRENT_TIME'];
    }

    public function testCalendarParametersAreAllowed(): void
    {
        self::assertFalse(self::readsTheDatabaseClock('WHERE day = :today AND starts_at >= :dayStart'));
        self::assertFalse(self::readsTheDatabaseClock('SELECT CAST(:day AS date), created_at::timestamptz'));
    }

    private static function readsTheDatabaseClock(string $text): bool
    {
        return preg_match(self::DATABASE_CLOCK, $text) === 1;
    }

    /**
     * @return iterable<string>
     */
    private static function stringsInSrc(): iterable
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, levels: 2) . '/src'));

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (!is_array($token) || !in_array($token[0], self::STRING_TOKENS, strict: true)) {
                    continue;
                }

                yield $file->getPathname() . ': ' . $token[1];
            }
        }
    }
}
