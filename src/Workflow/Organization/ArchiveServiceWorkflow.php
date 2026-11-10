<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\SchedulePauses;
use App\Context\Organization\Application\Service\ServiceArchives;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\People\Application\Service\Rosters;
use App\Library\Problem\Model\Ids;
use App\Workflow\Organization\Model\MemberCounts;
use App\Workflow\Organization\Model\ServiceOverview;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class ArchiveServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceArchives $archives,
        private SchedulePauses $pauses,
        private ServiceStages $stages,
        private Rosters $rosters,
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
        $service = $this->archives->archive($serviceId, pauseSchedules: $pause);

        return ServiceOverview::of(
            $service,
            $this->stages->activeOf($service),
            MemberCounts::in($this->rosters->serviceMembersToday([$service->id()]), $service),
        );
    }
}
