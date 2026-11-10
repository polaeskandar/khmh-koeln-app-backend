<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Context\Organization\Domain\Repository\StaffAssignmentRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class StaffAssignments
{
    public function __construct(
        private StaffAssignmentRepositoryInterface $assignments,
    ) {
    }

    /**
     * @param list<Uuid> $accountIds
     *
     * @return ReadableCollection<int, StaffAssignment>
     */
    public function ofAccounts(array $accountIds): ReadableCollection
    {
        return $this->assignments->ofAccounts($accountIds);
    }

    /**
     * @return ReadableCollection<int, StaffAssignment> the service's own first, then by stage position
     */
    public function ofService(Uuid $serviceId): ReadableCollection
    {
        return $this->assignments->ofService($serviceId);
    }

    /**
     * @param list<Uuid> $serviceIds the services whose own rosters the groups are on
     * @param list<Uuid> $stageIds
     *
     * @return ReadableCollection<int, StaffAssignment>
     */
    public function covering(array $serviceIds, array $stageIds): ReadableCollection
    {
        return $this->assignments->covering($serviceIds, $stageIds);
    }

    public function get(Uuid $id): StaffAssignment
    {
        return $this->assignments->find($id) ?? throw NotFound::of('assignment');
    }
}
