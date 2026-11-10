<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\ServiceMeetings;
use App\Context\Meetings\Domain\Query\StructureUsageQueryInterface;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Enum\ServiceFilter;
use App\Context\People\Application\Service\Attendees;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Client\Calendar;
use App\Workflow\Meetings\Model\SessionTallies;
use App\Workflow\Organization\Model\RecentAttendance;
use App\Workflow\Organization\Model\ServiceInsight;
use App\Workflow\Organization\Model\ServiceInsights;

final readonly class ListServiceInsightsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private ServiceMeetings $meetings,
        private Attendees $attendees,
        private StructureUsageQueryInterface $usage,
        private Calendar $calendar,
    ) {
    }

    /**
     * @return list<ServiceInsight>
     */
    public function __invoke(string $status): array
    {
        $scope = $this->access->requireScope(Action::Read, Resource::Services);
        $filter = ServiceFilter::tryFrom($status) ?? throw InvalidInput::field('status', 'validation.invalidChoice');

        $next = $this->meetings->listNextByService(ArchivedStructure::of($this->structure->archivedIds()), $scope);
        $completed = $this->meetings->listRecentlyCompleted($scope);
        $keys = SessionTallies::rosterKeysOf($completed->sessions, $completed->schedules);

        $tallies = SessionTallies::of(
            $this->attendees->listRostersOn($keys),
            $completed->present,
            $completed->sessions,
            $completed->schedules,
        );

        return ServiceInsights::of(
            $this->structure->listed($filter)->filter(static fn (Service $service): bool => $scope->reaches(
                $service->id(),
            )),
            $next,
            $this->structure->listStages(ServiceInsights::stageIdsOf($next)),
            new RecentAttendance($completed->sessions, $tallies),
            $this->usage->listImpactByService($this->calendar->today()),
        );
    }
}
