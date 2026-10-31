<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools\CodingStandard\Sniffs\Spacing;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\DummyFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Util\Tokens;
use PHPUnit\Framework\TestCase;

final class MultiLineStatementSpacingSniffTest extends TestCase
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

    public function testAStatementOverSeveralLinesIsSetApartFromItsNeighbours(): void
    {
        $file = self::sniffed(<<<'PHP'
            <?php
            function create(): void
            {
                $email = 'sara@example.org';
                $user = new User(
                    $email,
                );
                save($user);
            }
            PHP);

        self::assertSame([5, 7], self::errorLines($file));

        $file->fixer->fixFile();

        self::assertSame(<<<'PHP'
            <?php
            function create(): void
            {
                $email = 'sara@example.org';

                $user = new User(
                    $email,
                );

                save($user);
            }
            PHP, $file->fixer->getContents());
    }

    public function testFirstAndLastStatementsOfABlockAndSingleLinesNeedNoBlankLine(): void
    {
        $file = self::sniffed(<<<'PHP'
            <?php
            function create(): User
            {
                $user = new User(
                    'sara@example.org',
                );
                $name = 'Sara';
                $mail = 'sara';
                if ($user) {
                    return new User(
                        $name,
                    );
                }

                return $user;
            }
            PHP);

        self::assertSame([6], self::errorLines($file));
    }

    public function testDeclarationsOutsideFunctionsAndForLoopsAreLeftAlone(): void
    {
        $file = self::sniffed(<<<'PHP'
            <?php
            final class Limits
            {
                private const array LIMITS = [
                    'name' => 120,
                ];
                private const int MAX = 3;

                public function count(): void
                {
                    for ($i = 0; $i < 3; $i++) {
                        echo $i;
                    }
                }
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
        return str_contains(serialize($columns), 'CodingStandard.Spacing.MultiLineStatementSpacing');
    }
}
