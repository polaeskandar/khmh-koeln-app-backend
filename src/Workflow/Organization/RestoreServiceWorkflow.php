<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceArchives;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\People\Application\Service\Rosters;
use App\Workflow\Organization\Model\MemberCounts;
use App\Workflow\Organization\Model\ServiceOverview;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class RestoreServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceArchives $archives,
        private ServiceStages $stages,
        private Rosters $rosters,
    ) {
    }

    public function __invoke(string $id): ServiceOverview
    {
        $this->access->require(Action::Delete, Resource::Services, refusal: 'services.adminOnlyRestore');

        $this->access->requireIn(
            Action::Delete,
            Resource::Services,
            StructureGroup::ofServiceId($id),
            missing: 'service',
        );

        $service = $this->archives->restore($id);

        return ServiceOverview::of(
            $service,
            $this->stages->activeOf($service),
            MemberCounts::in($this->rosters->serviceMembersToday([$service->id()]), $service),
        );
    }
}
