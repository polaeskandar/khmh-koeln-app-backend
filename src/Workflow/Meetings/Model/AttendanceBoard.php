<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

final readonly class AttendanceBoard
{
    /**
     * @param list<BoardRow> $roster
     * @param list<VisitorRow> $notOnRoster
     * @param list<BoardRow> $recentlyMarked
     */
    public function __construct(
        public AttendanceCounts $counts,
        public array $roster,
        public array $notOnRoster,
        public array $recentlyMarked,
    ) {
    }

    public static function withoutLists(AttendanceCounts $counts): self
    {
        return new self($counts, roster: [], notOnRoster: [], recentlyMarked: []);
    }
}
