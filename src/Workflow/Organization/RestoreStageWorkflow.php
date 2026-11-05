<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StageArchives;
use App\Context\People\Application\Service\Rosters;
use App\Workflow\Organization\Model\StageDetails;

final readonly class RestoreStageWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StageArchives $archives,
        private Rosters $rosters,
    ) {
    }

    public function __invoke(string $id): StageDetails
    {
        $this->access->require(Action::Update, Resource::Stages);

        $stage = $this->archives->restore($id);

        return StageDetails::of($stage, $this->rosters->stageMembersToday([$stage->id()]));
    }
}
