<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Entity\Attendance;

final readonly class RecordedMark
{
    public function __construct(
        public Attendance $mark,
        public bool $created,
    ) {
    }
}
