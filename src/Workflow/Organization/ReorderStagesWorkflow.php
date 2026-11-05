<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Application\Service\StageOrder;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\Rosters;
use App\Workflow\Organization\Model\StageList;
use Symfony\Component\Uid\Uuid;

final readonly class ReorderStagesWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StageOrder $order,
        private ServiceStages $stages,
        private Rosters $rosters,
    ) {
    }

    /**
     * @param list<string> $stageIds
     */
    public function __invoke(string $serviceId, array $stageIds): StageList
    {
        $this->access->require(Action::Update, Resource::Stages);

        $service = $this->order->reorder($serviceId, $stageIds);
        $stages = $this->stages->ofService($service);

        return StageList::of(
            $service,
            $stages,
            $this->rosters->stageMembersToday(
                $stages->map(static fn (Stage $stage): Uuid => $stage->id())->getValues(),
            ),
        );
    }
}
