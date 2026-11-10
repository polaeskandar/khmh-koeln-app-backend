<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Context\Organization\Domain\Repository\StaffAssignmentRepositoryInterface;
use App\Platform\Access\Contract\StaffGroups;
use App\Platform\Access\Model\Group;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class AssignedGroups implements StaffGroups
{
    public function __construct(
        private StaffAssignmentRepositoryInterface $assignments,
    ) {
    }

    /**
     * @return list<Group>
     */
    #[Override]
    public function of(Uuid $accountId): array
    {
        return $this->assignments
            ->ofAccounts([$accountId])
            ->map(
                static fn (StaffAssignment $assignment): Group => new Group(
                    $assignment->service()->id(),
                    $assignment->stage()?->id(),
                ),
            )
            ->getValues();
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function coveringAccounts(Uuid $serviceId, ?Uuid $stageId): array
    {
        return $this->assignments
            ->covering([$serviceId], $stageId === null ? [] : [$stageId])
            ->map(static fn (StaffAssignment $assignment): Uuid => $assignment->accountId())
            ->getValues();
    }
}
