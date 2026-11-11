<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Repository;

use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Context\Organization\Domain\Query\StaffAssignmentQueryInterface;
use Symfony\Component\Uid\Uuid;

interface StaffAssignmentRepositoryInterface extends StaffAssignmentQueryInterface
{
    public function add(StaffAssignment $assignment): void;

    public function remove(StaffAssignment $assignment): void;

    public function removeStagesOf(Uuid $accountId, Uuid $serviceId): void;

    /**
     * @return ?StaffAssignment the account's assignment to the service as a whole, else the one to the stage
     */
    public function findCovering(Uuid $accountId, Uuid $serviceId, ?Uuid $stageId): ?StaffAssignment;
}
