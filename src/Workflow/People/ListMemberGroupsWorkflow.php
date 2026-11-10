<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Learning\Domain\Query\RecentLessonQueryInterface;
use App\Context\Meetings\Application\Service\MeetingSchedules;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
use App\Library\Time\Model\LocalDate;
use App\Workflow\People\Model\MemberGroupList;
use App\Workflow\People\Model\MemberRoster;
use Symfony\Component\Uid\Uuid;

final readonly class ListMemberGroupsWorkflow
{
    public function __construct(
        private MemberCheck $member,
        private MemberRecords $records,
        private StructureLookup $structure,
        private MeetingSchedules $schedules,
        private RecentLessonQueryInterface $lessons,
    ) {
    }

    public function __invoke(string $viewerId): MemberGroupList
    {
        $record = $this->records->ownRecord($this->member->require(Uuid::fromString($viewerId)));

        $roster = MemberRoster::of(
            $record,
            $this->structure->services(MemberRoster::serviceIdsOf($record)),
            $this->structure->stages(MemberRoster::stageIdsOf($record)),
        );

        return MemberGroupList::of(
            $roster,
            $this->schedules
                ->recurring(serviceId: null, stageId: null)
                ->filter(static fn (Schedule $schedule): bool => self::isCurrent($schedule, $roster->today)),
            $this->lessons->listLastTaughtIn($roster->stageIds(), $roster->today),
        );
    }

    private static function isCurrent(Schedule $schedule, LocalDate $today): bool
    {
        return $schedule->isActive() && $schedule->recurrence()->nextOccurrence($today) !== null;
    }
}
