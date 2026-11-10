<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Application\Service\StageLifecycle;
use App\Context\People\Domain\Query\RosterQueryInterface;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Workflow\Organization\Model\StageDetails;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class RestoreStageWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StageLifecycle $lifecycle,
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

        $stage = $this->lifecycle->restore($found->id());

        return StageDetails::of($stage, $this->rosters->countStageMembers([$stage->id()], $this->calendar->today()));
    }
}
