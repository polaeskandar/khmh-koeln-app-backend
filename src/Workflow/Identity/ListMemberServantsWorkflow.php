<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Identity\Application\Service\ServingAccounts;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Organization\Application\Service\StaffAssignments;
use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Context\People\Application\Service\MemberRecords;
use App\Workflow\Identity\Model\GroupServant;
use App\Workflow\Identity\Model\GroupServants;
use App\Workflow\People\Model\MemberRoster;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ListMemberServantsWorkflow
{
    public function __construct(
        private MemberCheck $member,
        private MemberRecords $records,
        private StaffAssignments $assignments,
        private ServingAccounts $accounts,
    ) {
    }

    /**
     * @return list<GroupServant>
     */
    public function __invoke(string $viewerId): array
    {
        $record = $this->records->ownRecord($this->member->require($viewerId));

        $covering = $this->assignments->covering(
            MemberRoster::serviceIdsOf($record),
            MemberRoster::stageIdsOf($record),
        );

        $leaderIds = $covering->filter(
            static fn (StaffAssignment $assignment): bool => $assignment->stage() === null,
        )->map(static fn (StaffAssignment $assignment): string => $assignment->accountId()->toRfc4122());

        $staff = new ArrayCollection(
            $this->accounts->activeAmong(
                $covering->map(static fn (StaffAssignment $assignment): Uuid => $assignment->accountId())->getValues(),
            ),
        );

        return GroupServants::of($staff->filter(static fn (User $user): bool => $leaderIds->contains(
            $user->id()->toRfc4122(),
        )), $staff);
    }
}
