<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Organization\Domain\ValueObject;

use App\Context\Organization\Domain\ValueObject\StageName;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class StageNameTest extends TestCase
{
    public function testANameIsCleanedAndKeyed(): void
    {
        $name = StageName::of("  3rd \u{00A0} Class ");

        self::assertSame('3rd Class', $name->value);
        self::assertSame('3rd class', $name->key);
    }

    public function testANameOfOnlySpacesIsMissing(): void
    {
        self::assertSame(
            ['name' => ['code' => 'stages.nameRequired']],
            ThrownFieldErrors::of(static fn (): StageName => StageName::of(" \u{00A0}\t")),
        );
    }

    public function testANameIsMeasuredAfterCleaning(): void
    {
        self::assertSame(80, StageName::of(' ' . str_repeat('a', times: 80) . '  ')->value |> mb_strlen(...));
        self::assertSame(
            ['name' => ['code' => 'validation.tooLong', 'params' => ['max' => 80]]],
            ThrownFieldErrors::of(static fn (): StageName => StageName::of(str_repeat('a', times: 81))),
        );
    }
}
