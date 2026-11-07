<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Workflow\Organization\Model\StageDetails;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class AddStageWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceStages $stages,
    ) {
    }

    public function __invoke(string $serviceId, string $name, ?string $description): StageDetails
    {
        $this->access->require(Action::Create, Resource::Stages);

        $stage = $this->stages->add($serviceId, $name, $description);

        return StageDetails::of($stage, new ArrayCollection());
    }
}
