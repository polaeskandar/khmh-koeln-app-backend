<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Workflow\Organization\Model\StageDetails;

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

        return StageDetails::of($this->stages->add($serviceId, $name, $description));
    }
}
