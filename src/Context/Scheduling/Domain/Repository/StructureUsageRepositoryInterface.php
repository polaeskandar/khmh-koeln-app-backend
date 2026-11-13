<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Repository;

use App\Context\Scheduling\Domain\Enum\SessionStatus;
use App\Context\Scheduling\Domain\Query\StructureUsageQueryInterface;
use Symfony\Component\Uid\Uuid;

interface StructureUsageRepositoryInterface extends StructureUsageQueryInterface
{
    /**
     * @param list<Uuid> $scheduleIds
     *
     * @return list<SessionStatus>
     */
    public function lockUnfinished(array $scheduleIds): array;

    public function lockSchedulesOf(?Uuid $serviceId, ?Uuid $stageId): void;
}
