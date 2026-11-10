<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceLifecycle;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\People\Domain\Query\RosterQueryInterface;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Workflow\Organization\Model\MemberCounts;
use App\Workflow\Organization\Model\ServiceOverview;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class RestoreServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceLifecycle $lifecycle,
        private ServiceStages $stages,
        private RosterQueryInterface $rosters,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $id): ServiceOverview
    {
        $this->access->require(Action::Delete, Resource::Services, refusal: 'services.adminOnlyRestore');
        $serviceId = Ids::toUuid($id, notFound: 'service');

        $this->access->requireIn(
            Action::Delete,
            Resource::Services,
            StructureGroup::ofServiceId($serviceId),
            missing: 'service',
        );

        $service = $this->lifecycle->restore($serviceId);

        return ServiceOverview::of(
            $service,
            $this->stages->listActiveOf($service),
            MemberCounts::in($this->rosters->countServiceMembers([$service->id()], $this->calendar->today()), $service),
        );
    }
}
