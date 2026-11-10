<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\People\Domain\Query\RosterQueryInterface;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Workflow\Organization\Model\StageDetails;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class EditStageWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceStages $stages,
        private RosterQueryInterface $rosters,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $stageId, string $name, ?string $description): StageDetails
    {
        $this->access->require(Action::Update, Resource::Stages);

        $found = $this->stages->get(Ids::toUuid($stageId, notFound: 'stage'));
        $this->access->requireIn(Action::Update, Resource::Stages, StructureGroup::ofStage($found), missing: 'stage');

        $stage = $this->stages->edit($found->id(), $name, $description);

        return StageDetails::of($stage, $this->rosters->countStageMembers([$stage->id()], $this->calendar->today()));
    }
}
