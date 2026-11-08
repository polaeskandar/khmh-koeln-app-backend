<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\ValueObject\SessionCounts;

final readonly class AttendanceCounts
{
    public function __construct(
        public int $present,
        public ?int $rosterSize,
        public int $rosterPresent,
        public int $notMarked,
        public int $notOnRoster,
    ) {
    }

    public static function of(SessionCounts $counts): self
    {
        return new self(
            $counts->present,
            $counts->rosterSize,
            $counts->rosterPresent,
            $counts->notMarked,
            $counts->notOnRoster,
        );
    }
}
