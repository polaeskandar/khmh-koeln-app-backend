<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Types\Model;

use App\Library\Types\Model\Type;
use PHPUnit\Framework\TestCase;

final class TypeTest extends TestCase
{
    public function testStringIsReturnedOnlyForAString(): void
    {
        self::assertSame('de', Type::of('de')->toStringOrNull());
        self::assertNull(Type::of(1)->toStringOrNull());
        self::assertNull(Type::of(null)->toStringOrNull());
    }
}
