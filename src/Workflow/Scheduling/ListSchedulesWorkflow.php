<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Context\Scheduling\Application\Service\ScheduleOutlooks;
use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Context\Scheduling\Domain\ValueObject\ArchivedStructure;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use App\Workflow\Scheduling\Model\RosterReferences;
use App\Workflow\Scheduling\Model\RosterSizes;
use App\Workflow\Scheduling\Model\ScheduleList;
use App\Workflow\Scheduling\Model\ScheduleSummary;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ListSchedulesWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ScheduleOutlooks $outlooks,
        private StructureLookup $structure,
        private Calendar $calendar,
        private Attendees $attendees,
    ) {
    }

    public function __invoke(?string $serviceId, ?string $stageId): ScheduleList
    {
        $scope = $this->access->requireScope(Action::Read, Resource::Services);

        $today = $this->calendar->today();
        $archived = ArchivedStructure::of($this->structure->archivedIds());

        $outlooks = $this->outlooks->listed(
            self::idOf('serviceId', $serviceId),
            self::idOf('stageId', $stageId),
            $archived,
            $today,
            $scope,
        );

        $schedules = RosterSizes::schedulesOf($outlooks);

        return new ScheduleList($today->toString(), ScheduleSummary::listOf(
            $outlooks,
            $this->rosterReferencesOf($schedules),
            $this->sizesOf($schedules, $today),
        ));
    }

    private static function idOf(string $field, ?string $value): ?Uuid
    {
        if ($value === null) {
            return null;
        }

        return Ids::toUuidOrNull($value) ?? throw InvalidInput::field($field, 'validation.invalidType');
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
