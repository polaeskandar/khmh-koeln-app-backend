<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Types\Model;

use App\Library\Types\Model\Integers;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class IntegersTest extends TestCase
{
    public function testAnIntegerIsReturnedAsItIs(): void
    {
        self::assertSame(3, Integers::of(3));
    }

    public function testAnythingElseIsRefused(): void
    {
        $this->expectException(UnexpectedValueException::class);

        Integers::of('3');
    }
}
