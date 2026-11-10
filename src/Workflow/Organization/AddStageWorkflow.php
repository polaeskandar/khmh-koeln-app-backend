<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ChurchServices;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Library\Problem\Model\Ids;
use App\Workflow\Organization\Model\StageDetails;
use App\Workflow\Organization\Model\StructureGroup;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class AddStageWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceStages $stages,
        private ChurchServices $services,
    ) {
    }

    public function __invoke(string $serviceId, string $name, ?string $description): StageDetails
    {
        $this->access->require(Action::Create, Resource::Stages);

        $service = $this->services->get(Ids::toUuid($serviceId, notFound: 'service'));
        $group = StructureGroup::ofService($service);
        $this->access->requireIn(Action::Create, Resource::Stages, $group, missing: 'service');

        $stage = $this->stages->add($service->id(), $name, $description);

        return StageDetails::of($stage, new ArrayCollection());
    }
}
