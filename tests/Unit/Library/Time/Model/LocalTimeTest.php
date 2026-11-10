<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Time\Model;

use App\Library\Time\Exception\InvalidLocalTime;
use App\Library\Time\Model\LocalTime;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocalTimeTest extends TestCase
{
    public function testAValidTimeKeepsItsText(): void
    {
        self::assertSame('18:30', LocalTime::of('18:30')->toString());
        self::assertSame('"00:00"', json_encode(LocalTime::of('00:00'), JSON_THROW_ON_ERROR));
        self::assertSame('23:59', LocalTime::of('23:59')->toString());
    }

    #[DataProvider('invalidTimes')]
    public function testAnythingButHoursAndMinutesOfOneDayIsRejected(string $value): void
    {
        $this->expectException(InvalidLocalTime::class);

        LocalTime::of($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTimes(): iterable
    {
        yield 'empty' => [''];
        yield 'end of day' => ['24:00'];
        yield 'unpadded' => ['7:5'];
        yield 'minute sixty' => ['18:60'];
        yield 'with seconds' => ['18:30:00'];
        yield 'twelve hour' => ['6:30 pm'];
    }

    public function testTheTimeOfAnInstantDropsItsSeconds(): void
    {
        self::assertSame('09:15', LocalTime::ofInstant(CarbonImmutable::parse('2026-11-08 09:15:42'))->toString());
        self::assertSame('09:15:00', LocalTime::of('09:15')->format('H:i:s'));
    }
}
