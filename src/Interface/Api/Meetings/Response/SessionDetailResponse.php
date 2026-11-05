<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Workflow\Meetings\Model\HistoryEntry;
use App\Workflow\Meetings\Model\ScheduleBrief;
use App\Workflow\Meetings\Model\SessionDetail;

final readonly class SessionDetailResponse
{
    /**
     * @param list<HistoryEntry> $history
     */
    public function __construct(
        public string $today,
        public SessionResponse $session,
        public ScheduleBrief $schedule,
        public array $history,
    ) {
    }

    public static function of(SessionDetail $detail): self
    {
        return new self($detail->today, new SessionResponse($detail->session), $detail->schedule, $detail->history);
    }
}
