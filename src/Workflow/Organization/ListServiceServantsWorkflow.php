<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\ServingAccounts;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceWrites;
use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Context\Organization\Domain\Query\StaffAssignmentQueryInterface;
use App\Library\Problem\Model\Ids;
use App\Workflow\Organization\Model\ServiceServants;
use App\Workflow\Organization\Model\StructureGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ListServiceServantsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceWrites $services,
        private StaffAssignmentQueryInterface $assignments,
        private ServingAccounts $accounts,
        private AccountLookup $lookup,
    ) {
    }

    public function __invoke(string $serviceId): ServiceServants
    {
        $this->access->require(Action::Read, Resource::Services);

        $service = $this->services->get(Ids::toUuid($serviceId, notFound: 'service'));
        $this->access->requireReaching(Action::Read, Resource::Services, $service->id(), missing: 'service');
        $this->access->requireAssigningIn(StructureGroup::ofService($service));

        $assignments = $this->assignments->listOfService($service->id());

        $servants = $this->accounts->listActiveAmong(
            $assignments->map(static fn (StaffAssignment $assignment): Uuid => $assignment->accountId())->getValues(),
        );

        return ServiceServants::of(
            $this->lookup->named(new ArrayCollection($servants)),
            $assignments,
            $this->lookup->named(new ArrayCollection($this->accounts->listActive())),
        );
    }
}
