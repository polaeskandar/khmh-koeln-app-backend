<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Meetings\Domain\ValueObject\StructureImpact;

final readonly class ArchiveImpact
{
    public function __construct(
        public int $activeSchedules,
        public int $upcomingSessions,
    ) {
    }

    public static function of(?StructureImpact $impact): self
    {
        return new self($impact->activeSchedules ?? 0, $impact->upcomingSessions ?? 0);
    }
}
