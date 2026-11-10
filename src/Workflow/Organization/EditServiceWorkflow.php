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

final readonly class EditServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ChurchServices $services,
        private ServiceStages $stages,
        private Rosters $rosters,
    ) {
    }

    public function __invoke(string $id, string $name, ?string $description): ServiceOverview
    {
        $this->access->require(Action::Update, Resource::Services);

        $found = $this->services->find(Ids::toUuid($id, notFound: 'service'));
        $group = StructureGroup::ofService($found);
        $this->access->requireIn(Action::Update, Resource::Services, $group, missing: 'service');

        $service = $this->services->edit($found->id(), $name, $description);

        return ServiceOverview::of(
            $service,
            $this->stages->activeOf($service),
            MemberCounts::in($this->rosters->serviceMembersToday([$service->id()]), $service),
        );
    }
}
