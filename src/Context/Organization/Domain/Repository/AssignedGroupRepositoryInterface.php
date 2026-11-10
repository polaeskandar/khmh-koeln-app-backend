<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Repository;

use Symfony\Component\Uid\Uuid;

interface AssignedGroupRepositoryInterface
{
    /**
     * @return list<array{serviceId: string, stageId: ?string}>
     */
    public function groupsOf(Uuid $accountId): array;

    /**
     * @return list<string> the accounts with an assignment of the service's own roster, or of the stage
     */
    public function accountsCovering(Uuid $serviceId, ?Uuid $stageId): array;
}
