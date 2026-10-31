<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\ValueObject\BirthDate;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class BirthDateTest extends TestCase
{
    public function testABirthDateUpToTodayIsKept(): void
    {
        $today = LocalDate::of('2026-11-08');

        self::assertSame('2026-11-08', BirthDate::of($today, $today)->value?->toString());
        self::assertNull(BirthDate::none()->value);
    }

    public function testABirthDateInTheFutureIsRejected(): void
    {
        self::assertSame(
            ['birthDate' => ['code' => 'validation.birthDateInFuture']],
            ThrownFieldErrors::of(static fn (): BirthDate => BirthDate::of(
                LocalDate::of('2026-11-09'),
                LocalDate::of('2026-11-08'),
            )),
        );
    }
}
