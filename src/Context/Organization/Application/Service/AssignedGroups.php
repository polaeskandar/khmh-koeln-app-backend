<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Repository\AssignedGroupRepositoryInterface;
use App\Platform\Access\Contract\StaffGroups;
use App\Platform\Access\Model\Group;
use Doctrine\Common\Collections\ArrayCollection;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class AssignedGroups implements StaffGroups
{
    public function __construct(
        private AssignedGroupRepositoryInterface $assignments,
    ) {
    }

    /**
     * @return list<Group>
     */
    #[Override]
    public function of(Uuid $accountId): array
    {
        return new ArrayCollection($this->assignments->groupsOf($accountId))->map(
            static fn (array $group): Group => new Group(
                Uuid::fromString($group['serviceId']),
                $group['stageId'] === null ? null : Uuid::fromString($group['stageId']),
            ),
        )->getValues();
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function coveringAccounts(Uuid $serviceId, ?Uuid $stageId): array
    {
        return new ArrayCollection($this->assignments->accountsCovering($serviceId, $stageId))->map(
            Uuid::fromString(...),
        )->getValues();
    }
}
