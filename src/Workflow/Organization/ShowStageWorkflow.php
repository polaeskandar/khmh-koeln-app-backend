<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\People\Domain\Query\RosterQueryInterface;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Workflow\Organization\Model\StageDetails;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class ShowStageWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceStages $stages,
        private RosterQueryInterface $rosters,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $stageId): StageDetails
    {
        $this->access->require(Action::Read, Resource::Services);

        $stage = $this->stages->get(Ids::toUuid($stageId, notFound: 'stage'));
        $this->access->requireIn(Action::Read, Resource::Services, StructureGroup::ofStage($stage), missing: 'stage');

        return StageDetails::of($stage, $this->rosters->countStageMembers([$stage->id()], $this->calendar->today()));
    }
}
