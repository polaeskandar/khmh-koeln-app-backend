<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Response;

use App\Context\Scheduling\Domain\Enum\RosterMode;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageReference;
use App\Workflow\Scheduling\Model\PlannedSession;
use DateTimeImmutable;
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
     *     kind: 'planned',
     *     scheduleId: string,
     *     title: string,
     *     service: ServiceReference|null,
     *     stage: StageReference|null,
     *     date: string,
     *     startTime: string,
     *     endTime: string|null,
     *     startsAt: DateTimeImmutable,
     *     endsAt: DateTimeImmutable|null,
     *     location: string|null,
     *     rosterMode: RosterMode,
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
        ];
    }
}
