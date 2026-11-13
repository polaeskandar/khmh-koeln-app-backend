<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Response;

use App\Context\Scheduling\Domain\Enum\RosterMode;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageReference;
use App\Workflow\Scheduling\Model\RecurrenceSummary;
use App\Workflow\Scheduling\Model\ScheduleSummary;
use App\Workflow\Scheduling\Model\UpcomingDate;
use DateTimeImmutable;
use JsonSerializable;
use Override;

final readonly class ScheduleResponse implements JsonSerializable
{
    public function __construct(
        private ScheduleSummary $summary,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     description: string|null,
     *     service: ServiceReference|null,
     *     stage: StageReference|null,
     *     recurrence: RecurrenceSummary,
     *     startTime: string,
     *     endTime: string|null,
     *     location: string|null,
     *     rosterMode: RosterMode,
     *     active: bool,
     *     generating: bool,
     *     nextDates: list<UpcomingDate>,
     *     sessionCount: int,
     *     rosterSize: int|null,
     *     createdAt: DateTimeImmutable,
     *     updatedAt: DateTimeImmutable,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $details = $this->summary->details;
        $roster = $this->summary->roster;
        $timing = $this->summary->timing;

        return [
            'id' => $details->id,
            'name' => $details->name,
            'description' => $details->description,
            'service' => $roster->service,
            'stage' => $roster->stage,
            'recurrence' => $timing->recurrence,
            'startTime' => $timing->startTime,
            'endTime' => $timing->endTime,
            'location' => $details->location,
            'rosterMode' => $roster->mode,
            ...$this->activityAndRecord(),
        ];
    }

    /**
     * @return array{
     *     active: bool,
     *     generating: bool,
     *     nextDates: list<UpcomingDate>,
     *     sessionCount: int,
     *     rosterSize: int|null,
     *     createdAt: DateTimeImmutable,
     *     updatedAt: DateTimeImmutable,
     * }
     */
    private function activityAndRecord(): array
    {
        $activity = $this->summary->activity;
        $record = $this->summary->record;

        return [
            'active' => $activity->active,
            'generating' => $activity->generating,
            'nextDates' => $activity->nextDates,
            'sessionCount' => $activity->sessionCount,
            'rosterSize' => $activity->rosterSize,
            'createdAt' => $record->createdAt,
            'updatedAt' => $record->updatedAt,
        ];
    }
}
