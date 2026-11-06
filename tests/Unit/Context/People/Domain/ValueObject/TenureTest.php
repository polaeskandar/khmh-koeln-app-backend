<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\People\Domain\ValueObject;

use App\Context\People\Domain\ValueObject\Tenure;
use App\Library\Time\Model\LocalDate;
use PHPUnit\Framework\TestCase;

final class TenureTest extends TestCase
{
    public function testATenureIncludesItsFirstToItsLastDay(): void
    {
        $tenure = Tenure::from(LocalDate::of('2026-09-01'))->endedFrom(LocalDate::of('2026-11-08'));
        self::assertNotNull($tenure);

        self::assertFalse($tenure->includes(LocalDate::of('2026-08-31')));
        self::assertTrue($tenure->includes(LocalDate::of('2026-09-01')));
        self::assertTrue($tenure->includes(LocalDate::of('2026-11-07')));
        self::assertFalse($tenure->includes(LocalDate::of('2026-11-08')));
        self::assertTrue(Tenure::from(LocalDate::of('2026-09-01'))->includes(LocalDate::of('2030-01-01')));
    }
}
