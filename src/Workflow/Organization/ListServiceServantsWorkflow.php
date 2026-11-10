<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\ServingAccounts;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ChurchServices;
use App\Context\Organization\Application\Service\StaffAssignments;
use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Library\Problem\Model\Ids;
use App\Workflow\Organization\Model\ServiceServants;
use App\Workflow\Organization\Model\StructureGroup;
use Symfony\Component\Uid\Uuid;

final readonly class ListServiceServantsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ChurchServices $services,
        private StaffAssignments $assignments,
        private ServingAccounts $accounts,
    ) {
    }

    public function __invoke(string $serviceId): ServiceServants
    {
        $this->access->require(Action::Read, Resource::Services);

        $service = $this->services->find(Ids::toUuid($serviceId, notFound: 'service'));
        $this->access->requireReaching(Action::Read, Resource::Services, $service->id(), missing: 'service');
        $this->access->requireAssigningIn(StructureGroup::ofService($service));

        $assignments = $this->assignments->ofService($service->id());

        $servants = $this->accounts->activeAmong(
            $assignments->map(static fn (StaffAssignment $assignment): Uuid => $assignment->accountId())->getValues(),
        );

        return ServiceServants::of($servants, $assignments, $this->accounts->active());
    }
}
