<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Workflow\Meetings\Model\AttendanceBoard;
use App\Workflow\Meetings\Model\AttendanceCounts;
use App\Workflow\Meetings\Model\BoardRow;
use App\Workflow\Meetings\Model\VisitorRow;
use JsonSerializable;
use Override;

final readonly class AttendanceBoardResponse implements JsonSerializable
{
    public function __construct(
        private AttendanceBoard $board,
    ) {
    }

    /**
     * @return array{
     *     counts: AttendanceCounts,
     *     roster: list<BoardRow>,
     *     notOnRoster: list<VisitorRow>,
     *     recentlyMarked: list<BoardRow>,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'counts' => $this->board->counts,
            'roster' => $this->board->roster,
            'notOnRoster' => $this->board->notOnRoster,
            'recentlyMarked' => $this->board->recentlyMarked,
        ];
    }
}
