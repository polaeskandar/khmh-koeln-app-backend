<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Workflow\Meetings\Model\PlannedSession;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageSummary;
use JsonSerializable;
use Override;

final readonly class PlannedSessionResponse implements JsonSerializable
{
    public function __construct(
        private PlannedSession $planned,
    ) {
    }

    /**
     * @return array{
     *     kind: string,
     *     scheduleId: string,
     *     title: string,
     *     service: ServiceReference|null,
     *     stage: StageSummary|null,
     *     date: string,
     *     startTime: string,
     *     endTime: string|null,
     *     startsAt: string,
     *     endsAt: string|null,
     *     location: string|null,
     *     rosterMode: string,
     *     can: array{manage: bool},
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $schedule = $this->planned->schedule;
        $roster = $this->planned->roster;
        $times = $this->planned->times;

        return [
            'kind' => 'planned',
            'scheduleId' => $schedule->id,
            'title' => $schedule->name,
            'service' => $roster->service,
            'stage' => $roster->stage,
            'date' => $times->date,
            'startTime' => $times->startTime,
            'endTime' => $times->endTime,
            'startsAt' => $times->startsAt,
            'endsAt' => $times->endsAt,
            'location' => $schedule->location,
            'rosterMode' => $roster->mode,
            'can' => ['manage' => $this->planned->can->manage],
        ];
    }
}
