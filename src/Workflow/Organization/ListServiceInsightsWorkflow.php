<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\ServiceMeetings;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\ChurchServices;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Workflow\Attendance\Model\SessionTallies;
use App\Workflow\Organization\Model\ServiceInsight;
use App\Workflow\Organization\Model\ServiceInsights;

final readonly class ListServiceInsightsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ChurchServices $services,
        private StructureLookup $structure,
        private ServiceMeetings $meetings,
        private Attendees $attendees,
    ) {
    }

    /**
     * @return list<ServiceInsight>
     */
    public function __invoke(string $status): array
    {
        $this->access->require(Action::Read, Resource::Services);

        $next = $this->meetings->nextByService(ArchivedStructure::of($this->structure->archivedIds()));
        $completed = $this->meetings->recentlyCompleted();
        $keys = SessionTallies::rosterKeysOf($completed->sessions, $completed->schedules);

        $tallies = SessionTallies::of(
            $this->attendees->rostersOn($keys),
            $completed->present,
            $completed->sessions,
            $completed->schedules,
        );

        return ServiceInsights::of(
            $this->services->listed($status),
            $next,
            $completed->sessions,
            $tallies,
            $this->structure->stages(ServiceInsights::stageIdsOf($next)),
        );
    }
}
