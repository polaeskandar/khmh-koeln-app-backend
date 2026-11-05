<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Types\Model;

use App\Library\Types\Model\Ratio;
use PHPUnit\Framework\TestCase;

final class RatioTest extends TestCase
{
    public function testARatioHasTwoDecimalsAndNothingOverNothingIsNull(): void
    {
        self::assertSame(0.67, Ratio::of(2, whole: 3));
        self::assertSame(1.0, Ratio::of(4, whole: 4));
        self::assertNull(Ratio::of(0, whole: 0));
    }

    public function testAMeanRoundsToTheNearestWhole(): void
    {
        self::assertSame(8, Ratio::mean(23, count: 3));
        self::assertSame(3, Ratio::mean(5, count: 2));
        self::assertNull(Ratio::mean(0, count: 0));
    }
}
