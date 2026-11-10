<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\SchedulePauses;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Application\Service\StageArchives;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\Rosters;
use App\Library\Problem\Model\Ids;
use App\Workflow\Organization\Model\StageDetails;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class ArchiveStageWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StageArchives $archives,
        private SchedulePauses $pauses,
        private Rosters $rosters,
        private ServiceStages $stages,
    ) {
    }

    public function __invoke(string $id): StageDetails
    {
        $this->access->require(Action::Update, Resource::Stages);
        $found = $this->stages->get(Ids::toUuid($id, notFound: 'stage'));
        $this->access->requireIn(Action::Update, Resource::Stages, StructureGroup::ofStage($found), missing: 'stage');

        $pause = fn (Stage $stage) => $this->pauses->pauseStage($stage->id(), $stage->name()->value);
        $stage = $this->archives->archive($found->id(), pauseSchedules: $pause);

        return StageDetails::of($stage, $this->rosters->stageMembersToday([$stage->id()]));
    }
}
