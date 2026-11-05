<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\People\Application\Service\Rosters;
use App\Workflow\Organization\Model\StageDetails;

final readonly class EditStageWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceStages $stages,
        private Rosters $rosters,
    ) {
    }

    public function __invoke(string $stageId, string $name, ?string $description): StageDetails
    {
        $this->access->require(Action::Update, Resource::Stages);

        $stage = $this->stages->edit($stageId, $name, $description);

        return StageDetails::of($stage, $this->rosters->stageMembersToday([$stage->id()]));
    }
}
