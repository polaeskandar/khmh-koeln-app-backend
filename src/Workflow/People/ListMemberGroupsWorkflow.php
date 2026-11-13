<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Learning\Domain\Query\RecentLessonQueryInterface;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
use App\Context\Scheduling\Application\Service\MeetingSchedules;
use App\Context\Scheduling\Domain\Entity\Schedule;
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
        $record = $this->records->getOwnRecord($this->member->require(Uuid::fromString($viewerId)));

        $roster = MemberRoster::of(
            $record,
            $this->structure->listServices(MemberRoster::serviceIdsOf($record)),
            $this->structure->listStages(MemberRoster::stageIdsOf($record)),
        );

        return MemberGroupList::of(
            $roster,
            $this->schedules
                ->listRecurring(serviceId: null, stageId: null)
                ->filter(static fn (Schedule $schedule): bool => self::isCurrent($schedule, $roster->today)),
            $this->lessons->listLastTaughtIn($roster->stageIds(), $roster->today),
        );
    }

    private static function isCurrent(Schedule $schedule, LocalDate $today): bool
    {
        return $schedule->isActive() && $schedule->recurrence()->nextOccurrence($today) !== null;
    }
}
