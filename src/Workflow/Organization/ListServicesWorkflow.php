<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ChurchServices;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\Rosters;
use App\Workflow\Organization\Model\MemberCounts;
use App\Workflow\Organization\Model\ServiceAbilities;
use App\Workflow\Organization\Model\ServiceList;
use App\Workflow\Organization\Model\ServiceOverview;
use Symfony\Component\Uid\Uuid;

final readonly class ListServicesWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ChurchServices $services,
        private ServiceStages $stages,
        private Rosters $rosters,
    ) {
    }

    public function __invoke(string $status): ServiceList
    {
        $this->access->require(Action::Read, Resource::Services);

        $services = $this->services->listed($status);
        $stages = $this->stages->activeIn($services);
        $can = ServiceAbilities::grantedBy($this->access);

        $members = $this->rosters->serviceMembersToday(
            $services->map(static fn (Service $service): Uuid => $service->id())->getValues(),
        );

        return new ServiceList(
            items: $services->map(static fn (Service $service): ServiceOverview => ServiceOverview::of(
                $service,
                $stages->filter(static fn (Stage $stage): bool => $stage->service() === $service),
                MemberCounts::in($members, $service),
                $can,
            ))->getValues(),
            canCreate: $this->access->allows(Action::Create, Resource::Services),
        );
    }
}
