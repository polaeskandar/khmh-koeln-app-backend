<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Application\Service\ServiceWrites;
use App\Context\People\Domain\Query\RosterQueryInterface;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Workflow\Organization\Model\MemberCounts;
use App\Workflow\Organization\Model\ServiceOverview;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class EditServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceWrites $services,
        private ServiceStages $stages,
        private RosterQueryInterface $rosters,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $id, string $name, ?string $description): ServiceOverview
    {
        $this->access->require(Action::Update, Resource::Services);

        $found = $this->services->get(Ids::toUuid($id, notFound: 'service'));
        $group = StructureGroup::ofService($found);
        $this->access->requireIn(Action::Update, Resource::Services, $group, missing: 'service');

        $service = $this->services->edit($found->id(), $name, $description);

        return ServiceOverview::of(
            $service,
            $this->stages->listActiveOf($service),
            MemberCounts::in($this->rosters->countServiceMembers([$service->id()], $this->calendar->today()), $service),
        );
    }
}
