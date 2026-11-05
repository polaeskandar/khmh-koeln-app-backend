<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

final readonly class StructureImpact
{
    public function __construct(
        public int $activeSchedules = 0,
        public int $upcomingSessions = 0,
    ) {
    }
}
