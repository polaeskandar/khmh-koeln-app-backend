<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\CodingStandard\Sniffs\Commenting;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\DummyFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Util\Tokens;
use PHPUnit\Framework\TestCase;

final class NoCommentsSniffTest extends TestCase
{
    #[\Override]
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, levels: 6) . '/vendor/squizlabs/php_codesniffer/autoload.php';
        self::assertTrue(class_exists(Tokens::class));

        foreach ([
            'PHP_CODESNIFFER_CBF' => false,
            'PHP_CODESNIFFER_VERBOSITY' => 0,
            'PHP_CODESNIFFER_IN_TESTS' => true,
        ] as $name => $value) {
            if (defined($name)) {
                continue;
            }

            define($name, $value);
        }
    }

    public function testLineAndBlockCommentsAreRejected(): void
    {
        $file = self::sniffed(<<<'PHP'
            <?php
            // The account's display name.
            $name = 'Sara';
            # Kept for later.
            $email = 'sara@example.org';
            /* The id. */
            $id = 7;
            PHP);

        self::assertSame([2, 4, 6], self::errorLines($file));
    }

    public function testADocblockWithASummaryIsRejected(): void
    {
        $file = self::sniffed(<<<'PHP'
            <?php
            /**
             * Lists the accounts.
             *
             * @return list<string>
             */
            function accounts(): array
            {
                return [];
            }
            PHP);

        self::assertSame([3], self::errorLines($file));
    }

    public function testTypeDocblocksAndToolMarkersStay(): void
    {
        $file = self::sniffed(<<<'PHP'
            <?php
            /**
             * @param array{
             *     name: string,
             *     email: string,
             * } $account
             *
             * @return list<string>
             */
            function names(array $account): array
            {
                // @mago-expect lint:no-redundant-variable
                $names = [$account['name']];

                return $names;
            }
            PHP);

        self::assertSame([], self::errorLines($file));
    }

    private static function sniffed(string $code): DummyFile
    {
        $config = new Config([
            '-q',
            '--standard=' . dirname(__DIR__, levels: 6) . '/tools/CodingStandard/ruleset.xml',
        ], dieOnUnknownArg: false);

        $file = new DummyFile($code, new Ruleset($config), $config);
        $file->process();

        return $file;
    }

    /**
     * @return list<array-key>
     */
    private static function errorLines(DummyFile $file): array
    {
        return array_keys(array_filter($file->getErrors(), self::hasSniffError(...)));
    }

    /**
     * @param array<array-key, mixed> $columns
     */
    private static function hasSniffError(array $columns): bool
    {
        return str_contains(serialize($columns), 'CodingStandard.Commenting.NoComments');
    }
}
