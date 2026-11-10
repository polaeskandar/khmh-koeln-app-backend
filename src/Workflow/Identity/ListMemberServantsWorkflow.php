<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Identity\Application\Service\ServingAccounts;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Context\Organization\Domain\Query\StaffAssignmentQueryInterface;
use App\Context\People\Application\Service\MemberRecords;
use App\Workflow\Identity\Model\GroupServant;
use App\Workflow\Identity\Model\GroupServants;
use App\Workflow\Identity\Model\ServantGroups;
use App\Workflow\People\Model\MemberRoster;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ListMemberServantsWorkflow
{
    public function __construct(
        private MemberCheck $member,
        private MemberRecords $records,
        private StructureLookup $structure,
        private StaffAssignmentQueryInterface $assignments,
        private ServingAccounts $accounts,
    ) {
    }

    /**
     * @return list<GroupServant>
     */
    public function __invoke(string $viewerId): array
    {
        $record = $this->records->getOwnRecord($this->member->require(Uuid::fromString($viewerId)));
        $serviceIds = MemberRoster::serviceIdsOf($record);
        $stageIds = MemberRoster::stageIdsOf($record);
        $covering = $this->assignments->listCovering($serviceIds, $stageIds);

        $staff = $this->accounts->listActiveAmong(
            $covering->map(static fn (StaffAssignment $assignment): Uuid => $assignment->accountId())->getValues(),
        );

        return GroupServants::of(
            new ArrayCollection($staff),
            ServantGroups::of(
                $record->currentMemberships(),
                $this->structure->listServices($serviceIds),
                $this->structure->listStages($stageIds),
                $covering,
            ),
        );
    }
}
