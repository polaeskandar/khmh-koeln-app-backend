<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Context\Scheduling\Application\Service\CoveredSchedules;
use App\Context\Scheduling\Application\Service\ScheduleOutlooks;
use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Context\Scheduling\Domain\ValueObject\ArchivedStructure;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use App\Workflow\Scheduling\Model\DatedSchedule;
use App\Workflow\Scheduling\Model\RosterReferences;
use App\Workflow\Scheduling\Model\RosterSizes;
use App\Workflow\Scheduling\Model\ScheduleSummary;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ShowScheduleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ScheduleOutlooks $outlooks,
        private StructureLookup $structure,
        private Calendar $calendar,
        private Attendees $attendees,
    ) {
    }

    public function __invoke(string $id): DatedSchedule
    {
        $this->access->require(Action::Read, Resource::Services);

        $today = $this->calendar->today();
        $archived = ArchivedStructure::of($this->structure->archivedIds());
        $outlook = $this->outlooks->get(Ids::toUuid($id, notFound: 'schedule'), $archived, $today);
        $group = CoveredSchedules::groupOf($outlook->schedule);
        $this->access->requireIn(Action::Read, Resource::Services, $group, missing: 'schedule');
        $schedules = RosterSizes::schedulesOf([$outlook]);

        return new DatedSchedule($today->toString(), ScheduleSummary::of(
            $outlook,
            $this->rosterReferencesOf($schedules),
            $this->sizesOf($schedules, $today),
        ));
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     */
    private function rosterReferencesOf(ReadableCollection $schedules): RosterReferences
    {
        return RosterReferences::of(
            $this->structure->listServices(RosterReferences::serviceIdsOf($schedules)),
            $this->structure->listStages(RosterReferences::stageIdsOf($schedules)),
        );
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     */
    private function sizesOf(ReadableCollection $schedules, LocalDate $today): RosterSizes
    {
        return new RosterSizes($this->attendees->listRostersOn(RosterSizes::keysOf($schedules, $today)), $today);
    }
}
