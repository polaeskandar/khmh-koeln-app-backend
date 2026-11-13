<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Response;

use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageSummary;
use App\Workflow\People\Model\GroupSchedule;
use App\Workflow\People\Model\LastLesson;
use App\Workflow\People\Model\MemberGroup;
use App\Workflow\Scheduling\Model\RecurrenceSummary;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Override;

final readonly class MemberGroupResponse implements JsonSerializable
{
    private function __construct(
        private MemberGroup $group,
    ) {
    }

    public static function of(MemberGroup $group): self
    {
        return new self($group);
    }

    /**
     * @return array{
     *     id: string,
     *     service: ServiceReference,
     *     stage: StageSummary|null,
     *     since: string,
     *     schedules: list<array{
     *         id: string,
     *         name: string,
     *         recurrence: RecurrenceSummary,
     *         startTime: string,
     *         endTime: string|null,
     *         location: string|null,
     *     }>,
     *     lastLesson: LastLesson|null,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $place = $this->group->place;

        return [
            'id' => $place->id,
            'service' => $place->service,
            'stage' => $place->stage,
            'since' => $place->since,
            'schedules' => new ArrayCollection($this->group->schedules)->map(self::scheduleOf(...))->getValues(),
            'lastLesson' => $this->group->lastLesson,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     recurrence: RecurrenceSummary,
     *     startTime: string,
     *     endTime: string|null,
     *     location: string|null,
     * }
     */
    private static function scheduleOf(GroupSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'recurrence' => $schedule->timing->recurrence,
            'startTime' => $schedule->timing->startTime,
            'endTime' => $schedule->timing->endTime,
            'location' => $schedule->location,
        ];
    }
}
