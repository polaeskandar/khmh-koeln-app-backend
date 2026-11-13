<?php

declare(strict_types=1);

namespace App\Platform\Access\Contract;

use Symfony\Component\Uid\Uuid;

interface ActiveAccounts
{
    public function isEnabled(Uuid $accountId): bool;

    /**
     * @param list<Uuid> $accountIds
     *
     * @return list<Uuid> the enabled accounts among them
     */
    public function enabledAmong(array $accountIds): array;

    /**
     * @param list<Uuid> $accountIds
     *
     * @return list<Uuid> the enabled accounts among them that no requested erasure waits for
     */
    public function remainingAmong(array $accountIds): array;
}
