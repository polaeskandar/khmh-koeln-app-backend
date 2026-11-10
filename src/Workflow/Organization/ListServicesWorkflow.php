<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Enum\ServiceFilter;
use App\Context\People\Application\Service\Rosters;
use App\Library\Problem\Exception\InvalidInput;
use App\Workflow\Organization\Model\MemberCounts;
use App\Workflow\Organization\Model\ServiceList;
use App\Workflow\Organization\Model\ServiceOverview;
use App\Workflow\Organization\Model\StructureGroup;
use Symfony\Component\Uid\Uuid;

final readonly class ListServicesWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $services,
        private ServiceStages $stages,
        private Rosters $rosters,
    ) {
    }

    public function __invoke(string $status): ServiceList
    {
        $scope = $this->access->scope(Action::Read, Resource::Services);

        $filter = ServiceFilter::tryFrom($status) ?? throw InvalidInput::field('status', 'validation.invalidChoice');

        $services = $this->services->listed($filter)->filter(static fn (Service $service): bool => $scope->reaches(
            $service->id(),
        ));

        $stages = $scope->filter($this->stages->activeIn($services), StructureGroup::ofStage(...));

        $members = $this->rosters->serviceMembersToday(
            $services->map(static fn (Service $service): Uuid => $service->id())->getValues(),
        );

        return new ServiceList(
            $services->map(static fn (Service $service): ServiceOverview => ServiceOverview::of(
                $service,
                $stages->filter(static fn (Stage $stage): bool => $stage->service() === $service),
                MemberCounts::in($members, $service),
            ))->getValues(),
        );
    }
}
