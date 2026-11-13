<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Application\Service\StageOrder;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Query\RosterQueryInterface;
use App\Context\Scheduling\Domain\Query\StructureUsageQueryInterface;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Workflow\Organization\Model\StageList;
use App\Workflow\Organization\Model\StructureGroup;
use Symfony\Component\Uid\Uuid;

final readonly class ReorderStagesWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StageOrder $order,
        private ServiceStages $stages,
        private RosterQueryInterface $rosters,
        private StructureUsageQueryInterface $usage,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param list<string> $stageIds
     */
    public function __invoke(string $serviceId, array $stageIds): StageList
    {
        $this->access->require(Action::Update, Resource::Stages);
        $id = Ids::toUuid($serviceId, notFound: 'service');

        $this->access->requireIn(
            Action::Update,
            Resource::Stages,
            StructureGroup::ofServiceId($id),
            missing: 'service',
        );

        $service = $this->order->reorder($id, $stageIds);
        $stages = $this->stages->listOfService($service);
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
