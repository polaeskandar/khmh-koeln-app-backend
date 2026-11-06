<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PhpToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EntitiesHoldOnlyStateTest extends TestCase
{
    private const array LOGIC = [
        T_IF => '`if`',
        T_ELSEIF => '`elseif`',
        T_ELSE => '`else`',
        T_SWITCH => '`switch`',
        T_MATCH => '`match`',
        T_FOR => '`for`',
        T_FOREACH => '`foreach`',
        T_WHILE => '`while`',
        T_DO => '`do`',
        T_TRY => '`try`',
        T_THROW => '`throw`',
        T_COALESCE => '`??`',
        T_COALESCE_EQUAL => '`??=`',
        T_BOOLEAN_AND => '`&&`',
        T_BOOLEAN_OR => '`||`',
        T_FN => 'a closure',
    ];

    private const array BEFORE_A_NULLABLE_TYPE = ['(', ',', ':', '|'];

    private const array TYPE_MODIFIERS = [T_PRIVATE, T_PUBLIC, T_PROTECTED, T_READONLY, T_VAR];

    private const array VALUE_OBJECT_BUILDERS = ['of', 'from', 'tryFrom'];

    #[DataProvider('entities')]
    public function testAnEntityHoldsStateWithoutLogic(string $path): void
    {
        self::assertSame(
            [],
            self::logicIn($path),
            basename($path) . ' holds only state; validation, rules and decisions belong in an application service.',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function entities(): iterable
    {
        $paths = glob(dirname(__DIR__, levels: 2) . '/src/Context/*/Domain/Entity/*.php');

        foreach ($paths === false ? [] : $paths as $path) {
            yield basename($path) => [$path];
        }
    }

    /**
     * @return list<string>
     */
    private static function logicIn(string $path): array
    {
        $parts = array_values(array_filter(
            PhpToken::tokenize((string) file_get_contents($path)),
            static fn (PhpToken $part): bool => !$part->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ));

        return array_values(array_filter(array_map(
            static fn (PhpToken $part, int $index): ?string => self::logicAt(
                $part,
                $parts[$index - 1] ?? null,
                $parts[$index + 1] ?? null,
            ),
            $parts,
            array_keys($parts),
        )));
    }

    private static function logicAt(PhpToken $part, ?PhpToken $previous, ?PhpToken $next): ?string
    {
        $logic = self::LOGIC[$part->id] ?? self::logicAround($part, $previous, $next);

        return $logic === null ? null : sprintf('line %d uses %s', $part->line, $logic);
    }

    private static function logicAround(PhpToken $part, ?PhpToken $previous, ?PhpToken $next): ?string
    {
        return match (true) {
            $part->text === '?' && !self::opensNullableType($previous) => 'a ternary',
            $part->is(T_STATIC) && $next?->is(T_FUNCTION) === true => 'a static factory',
            $part->is(T_FUNCTION) && $next?->text === '(' => 'a closure',
            $part->is(T_DOUBLE_COLON) && in_array($next?->text, self::VALUE_OBJECT_BUILDERS, strict: true)
                => 'a value object built from raw values',
            default => null,
        };
    }

    private static function opensNullableType(?PhpToken $previous): bool
    {
        $isTypePosition = in_array($previous?->text, self::BEFORE_A_NULLABLE_TYPE, strict: true);

        return $isTypePosition || $previous?->is(self::TYPE_MODIFIERS) === true;
    }
}
