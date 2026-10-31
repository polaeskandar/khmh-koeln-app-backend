<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Organization\Domain\ValueObject;

use App\Context\Organization\Domain\ValueObject\Description;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class DescriptionTest extends TestCase
{
    public function testADescriptionIsTrimmedAndBlankBecomesNull(): void
    {
        self::assertSame("Ages 8\n to 9", Description::of("  Ages 8\n to 9 ", max: 500)->value);
        self::assertNull(Description::of(" \n ", max: 500)->value);
        self::assertNull(Description::of(null, max: 500)->value);
    }

    public function testADescriptionHasALimit(): void
    {
        self::assertSame(
            ['description' => ['code' => 'validation.tooLong', 'params' => ['max' => 3]]],
            ThrownFieldErrors::of(static fn (): Description => Description::of('four', max: 3)),
        );
    }
}
