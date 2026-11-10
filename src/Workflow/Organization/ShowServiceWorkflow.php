<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ChurchServices;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\People\Application\Service\Rosters;
use App\Library\Problem\Model\Ids;
use App\Workflow\Organization\Model\MemberCounts;
use App\Workflow\Organization\Model\ServiceOverview;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class ShowServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ChurchServices $services,
        private ServiceStages $stages,
        private Rosters $rosters,
    ) {
    }

    public function __invoke(string $id): ServiceOverview
    {
        $scope = $this->access->scope(Action::Read, Resource::Services);

        $service = $this->services->find(Ids::toUuid($id, notFound: 'service'));
        $this->access->requireReaching(Action::Read, Resource::Services, $service->id(), missing: 'service');

        return ServiceOverview::of(
            $service,
            $scope->filter($this->stages->activeOf($service), StructureGroup::ofStage(...)),
            MemberCounts::in($this->rosters->serviceMembersToday([$service->id()]), $service),
        );
    }
}
