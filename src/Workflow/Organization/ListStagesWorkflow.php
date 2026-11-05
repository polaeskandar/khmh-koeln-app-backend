<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ChurchServices;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\People\Application\Service\Rosters;
use App\Workflow\Organization\Model\StageList;

final readonly class ListStagesWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ChurchServices $services,
        private ServiceStages $stages,
        private Rosters $rosters,
    ) {
    }

    public function __invoke(string $serviceId): StageList
    {
        $this->access->require(Action::Read, Resource::Services);

        return StageList::current($this->services->find($serviceId), $this->stages, $this->rosters, $this->access);
    }
}
