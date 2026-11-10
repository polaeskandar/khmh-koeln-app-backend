<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Application\Service\ServiceWrites;
use App\Context\People\Domain\Query\RosterQueryInterface;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Workflow\Organization\Model\MemberCounts;
use App\Workflow\Organization\Model\ServiceOverview;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class ShowServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceWrites $services,
        private ServiceStages $stages,
        private RosterQueryInterface $rosters,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $id): ServiceOverview
    {
        $scope = $this->access->requireScope(Action::Read, Resource::Services);

        $service = $this->services->get(Ids::toUuid($id, notFound: 'service'));
        $this->access->requireReaching(Action::Read, Resource::Services, $service->id(), missing: 'service');

        return ServiceOverview::of(
            $service,
            $scope->filter($this->stages->listActiveOf($service), StructureGroup::ofStage(...)),
            MemberCounts::in($this->rosters->countServiceMembers([$service->id()], $this->calendar->today()), $service),
        );
    }
}
