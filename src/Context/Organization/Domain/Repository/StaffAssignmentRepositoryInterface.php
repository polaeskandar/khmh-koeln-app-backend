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

    public function findHeld(Uuid $accountId, Uuid $serviceId, ?Uuid $stageId): ?StaffAssignment;
}
