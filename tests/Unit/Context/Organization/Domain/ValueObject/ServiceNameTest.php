<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Organization\Domain\ValueObject;

use App\Context\Organization\Domain\ValueObject\ServiceName;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class ServiceNameTest extends TestCase
{
    public function testANameIsCleanedAndKeyed(): void
    {
        $name = ServiceName::of("  3rd \u{00A0} Class ");

        self::assertSame('3rd Class', $name->value);
        self::assertSame('3rd class', $name->key);
    }

    public function testANameOfOnlySpacesIsMissing(): void
    {
        self::assertSame(
            ['name' => ['code' => 'services.nameRequired']],
            ThrownFieldErrors::of(static fn (): ServiceName => ServiceName::of(" \u{00A0}\t")),
        );
    }

    public function testANameIsMeasuredAfterCleaning(): void
    {
        self::assertSame(120, ServiceName::of(' ' . str_repeat('a', times: 120) . '  ')->value |> mb_strlen(...));
        self::assertSame(
            ['name' => ['code' => 'validation.tooLong', 'params' => ['max' => 120]]],
            ThrownFieldErrors::of(static fn (): ServiceName => ServiceName::of(str_repeat('a', times: 121))),
        );
    }
}
