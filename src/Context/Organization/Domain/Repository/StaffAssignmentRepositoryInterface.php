<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Repository;

use App\Context\Organization\Domain\Entity\StaffAssignment;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface StaffAssignmentRepositoryInterface
{
    public function add(StaffAssignment $assignment): void;

    public function remove(StaffAssignment $assignment): void;

    public function find(Uuid $id): ?StaffAssignment;

    public function findHeld(Uuid $accountId, Uuid $serviceId, ?Uuid $stageId): ?StaffAssignment;

    /**
     * @param list<Uuid> $accountIds
     *
     * @return ReadableCollection<int, StaffAssignment> by service name, the service's own assignment first, then by
     *     stage position
     */
    public function ofAccounts(array $accountIds): ReadableCollection;

    /**
     * @return ReadableCollection<int, StaffAssignment> the assignments of the service and of its stages, the service's
     *     own first, then by stage position
     */
    public function ofService(Uuid $serviceId): ReadableCollection;

    /**
     * @param list<Uuid> $serviceIds
     * @param list<Uuid> $stageIds
     *
     * @return ReadableCollection<int, StaffAssignment> the assignments of the services' own rosters and of the stages
     */
    public function covering(array $serviceIds, array $stageIds): ReadableCollection;
}
