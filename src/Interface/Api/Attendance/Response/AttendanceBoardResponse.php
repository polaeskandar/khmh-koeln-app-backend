<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance\Response;

use App\Interface\Api\Meetings\Response\SessionResponse;
use App\Workflow\Attendance\Model\AttendanceBoard;
use App\Workflow\Attendance\Model\AttendanceCounts;
use App\Workflow\Attendance\Model\BoardRow;
use App\Workflow\Attendance\Model\Correction;
use App\Workflow\Attendance\Model\VisitorRow;
use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Meetings\Model\HistoryEntry;
use App\Workflow\People\Model\PersonReference;
use Doctrine\Common\Collections\ArrayCollection;
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
     *     today: string,
     *     session: array<string, scalar|object|array<string, bool>|null>,
     *     counts: AttendanceCounts,
     *     roster: list<BoardRow>,
     *     notOnRoster: list<VisitorRow>,
     *     recentlyMarked: list<BoardRow>,
     *     corrections: list<array<string, string|PersonReference|AccountReference|null>>,
     *     history: list<HistoryEntry>,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $board = $this->board;
        $session = $board->session;

        return [
            'today' => $board->today,
            'session' => [
                ...new SessionResponse($session->view)->jsonSerialize(),
                'openedAt' => $session->openedAt,
                'closedAt' => $session->closedAt,
                'closedBy' => $session->closedBy,
            ],
            'counts' => $board->counts,
            'roster' => $board->lists->roster,
            'notOnRoster' => $board->lists->notOnRoster,
            'recentlyMarked' => $board->lists->recentlyMarked,
            'corrections' => new ArrayCollection($board->lists->corrections)->map(self::correctionOf(...))->getValues(),
            'history' => $board->history,
        ];
    }

    /**
     * @return array<string, string|PersonReference|AccountReference|null>
     */
    private static function correctionOf(Correction $correction): array
    {
        return [
            'id' => $correction->mark->id,
            'person' => $correction->person,
            'recordedAt' => $correction->mark->recordedAt,
            'recordedBy' => $correction->mark->recordedBy,
            'removedAt' => $correction->removedAt,
            'removedBy' => $correction->removedBy,
            'reason' => $correction->reason,
        ];
    }
}
