<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Query;

use App\Context\Organization\Domain\Entity\StaffAssignment;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface StaffAssignmentQueryInterface
{
    public function find(Uuid $id): ?StaffAssignment;

    /**
     * @param list<Uuid> $accountIds
     *
     * @return ReadableCollection<int, StaffAssignment> by service name, the service's own assignment first, then by
     *     stage position
     */
    public function listOfAccounts(array $accountIds): ReadableCollection;

    /**
     * @return ReadableCollection<int, StaffAssignment> the assignments of the service and of its stages, the service's
     *     own first, then by stage position
     */
    public function listOfService(Uuid $serviceId): ReadableCollection;

    /**
     * @param list<Uuid> $serviceIds
     * @param list<Uuid> $stageIds
     *
     * @return ReadableCollection<int, StaffAssignment> the assignments of the services' own rosters and of the stages
     */
    public function listCovering(array $serviceIds, array $stageIds): ReadableCollection;
}
