<?php

declare(strict_types=1);

namespace App\Platform\Access\Contract;

use App\Platform\Access\Model\Group;
use Symfony\Component\Uid\Uuid;

interface StaffGroups
{
    /**
     * @return list<Group>
     */
    public function of(Uuid $accountId): array;

    /**
     * @return list<Uuid> the accounts with an assignment that covers the group
     */
    public function coveringAccounts(Uuid $serviceId, ?Uuid $stageId): array;
}
