<?php

declare(strict_types=1);

namespace App\Context\Attendance\Domain\ValueObject;

use App\Context\Attendance\Domain\Entity\Attendance;

final readonly class RecordedMark
{
    public function __construct(
        public Attendance $mark,
        public bool $created,
    ) {
    }
}
