<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Entity\AttendanceMark;

final readonly class RecordedMark
{
    public function __construct(
        public AttendanceMark $mark,
        public bool $created,
    ) {
    }
}
