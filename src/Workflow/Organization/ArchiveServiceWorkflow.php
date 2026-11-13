<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceLifecycle;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\People\Domain\Query\RosterQueryInterface;
use App\Context\Scheduling\Application\Service\SchedulePauses;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Workflow\Organization\Model\MemberCounts;
use App\Workflow\Organization\Model\ServiceOverview;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class ArchiveServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceLifecycle $lifecycle,
        private SchedulePauses $pauses,
        private ServiceStages $stages,
        private RosterQueryInterface $rosters,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $id): ServiceOverview
    {
        $this->access->require(Action::Delete, Resource::Services, refusal: 'services.adminOnlyArchive');
        $serviceId = Ids::toUuid($id, notFound: 'service');

        $this->access->requireIn(
            Action::Delete,
            Resource::Services,
            StructureGroup::ofServiceId($serviceId),
            missing: 'service',
        );

        $pause = fn (Service $service) => $this->pauses->pauseService($service->id(), $service->name()->value);
        $service = $this->lifecycle->archive($serviceId, pauseSchedules: $pause);

        return ServiceOverview::of(
            $service,
            $this->stages->listActiveOf($service),
            MemberCounts::in($this->rosters->countServiceMembers([$service->id()], $this->calendar->today()), $service),
        );
    }
}
