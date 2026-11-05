<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\SchedulePauses;
use App\Context\Organization\Application\Service\StageArchives;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\Rosters;
use App\Workflow\Organization\Model\StageDetails;

final readonly class ArchiveStageWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StageArchives $archives,
        private SchedulePauses $pauses,
        private Rosters $rosters,
    ) {
    }

    public function __invoke(string $id): StageDetails
    {
        $this->access->require(Action::Update, Resource::Stages);

        $stage = $this->archives->archive($id, pause: fn (Stage $stage): bool => $this->pauses->pauseStage(
            $stage->id(),
        ));

        return StageDetails::of($stage, $this->rosters->stageMembersToday([$stage->id()]));
    }
}
