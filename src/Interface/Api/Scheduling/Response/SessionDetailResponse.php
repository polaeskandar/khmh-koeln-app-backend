<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Response;

use App\Workflow\Scheduling\Model\HistoryEntry;
use App\Workflow\Scheduling\Model\ScheduleBrief;
use App\Workflow\Scheduling\Model\SessionDetail;
use App\Workflow\Scheduling\Model\SessionState;

final readonly class SessionDetailResponse
{
    /**
     * @param list<HistoryEntry> $history
     */
    public function __construct(
        public string $today,
        public SessionResponse $session,
        public SessionState $state,
        public ?ScheduleBrief $schedule,
        public array $history,
    ) {
    }

    public static function of(SessionDetail $detail): self
    {
        return new self(
            $detail->today,
            new SessionResponse($detail->session),
            $detail->state,
            $detail->schedule,
            $detail->history,
        );
    }
}
