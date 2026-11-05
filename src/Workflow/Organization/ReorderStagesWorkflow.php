<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\ServiceMeetings;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Application\Service\StageOrder;
use App\Context\People\Application\Service\Rosters;
use App\Workflow\Organization\Model\StageList;

final readonly class ReorderStagesWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StageOrder $order,
        private ServiceStages $stages,
        private Rosters $rosters,
        private ServiceMeetings $meetings,
    ) {
    }

    /**
     * @param list<string> $stageIds
     */
    public function __invoke(string $serviceId, array $stageIds): StageList
    {
        $this->access->require(Action::Update, Resource::Stages);

        $service = $this->order->reorder($serviceId, $stageIds);

        return StageList::current($service, $this->stages, $this->rosters, $this->meetings, $this->access);
    }
}
