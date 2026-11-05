<?php

declare(strict_types=1);

namespace App\Context\Attendance\Domain\ValueObject;

final readonly class ErasedAttendance
{
    public function __construct(
        public int $roster = 0,
        public int $present = 0,
        public int $rosterPresent = 0,
    ) {
    }
}
