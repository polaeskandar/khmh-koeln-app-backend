<?php

declare(strict_types=1);

namespace App\Interface\Api\MemberPortal\Response;

use App\Workflow\Meetings\Model\RecurrenceSummary;
use App\Workflow\MemberPortal\Model\GroupSchedule;
use App\Workflow\MemberPortal\Model\MemberGroup;
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
     * @return array<string, mixed>
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
     * @return array<string, string|RecurrenceSummary|null>
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
