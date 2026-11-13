<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Application\Service\StageLifecycle;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Query\RosterQueryInterface;
use App\Context\Scheduling\Application\Service\SchedulePauses;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Workflow\Organization\Model\StageDetails;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class ArchiveStageWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StageLifecycle $lifecycle,
        private SchedulePauses $pauses,
        private RosterQueryInterface $rosters,
        private ServiceStages $stages,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $id): StageDetails
    {
        $this->access->require(Action::Update, Resource::Stages);
        $found = $this->stages->get(Ids::toUuid($id, notFound: 'stage'));
        $this->access->requireIn(Action::Update, Resource::Stages, StructureGroup::ofStage($found), missing: 'stage');

        $pause = fn (Stage $stage) => $this->pauses->pauseStage($stage->id(), $stage->name()->value);
        $stage = $this->lifecycle->archive($found->id(), pauseSchedules: $pause);

        return StageDetails::of($stage, $this->rosters->countStageMembers([$stage->id()], $this->calendar->today()));
    }
}
