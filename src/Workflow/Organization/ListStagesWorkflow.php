<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Domain\Query\StructureUsageQueryInterface;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Application\Service\ServiceWrites;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Query\RosterQueryInterface;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Workflow\Organization\Model\StageList;
use App\Workflow\Organization\Model\StructureGroup;
use Symfony\Component\Uid\Uuid;

final readonly class ListStagesWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceWrites $services,
        private ServiceStages $stages,
        private RosterQueryInterface $rosters,
        private StructureUsageQueryInterface $usage,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $serviceId): StageList
    {
        $scope = $this->access->scope(Action::Read, Resource::Services);

        $service = $this->services->get(Ids::toUuid($serviceId, notFound: 'service'));
        $this->access->requireReaching(Action::Read, Resource::Services, $service->id(), missing: 'service');

        $stages = $scope->filter($this->stages->ofService($service), StructureGroup::ofStage(...));
        $ids = $stages->map(static fn (Stage $stage): Uuid => $stage->id())->getValues();
        $today = $this->calendar->today();

        return StageList::of(
            $service,
            $stages,
            $this->rosters->countStageMembers($ids, $today),
            $this->usage->listImpactOfStages($ids, $today),
        );
    }
}
