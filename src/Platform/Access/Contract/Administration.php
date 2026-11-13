<?php

declare(strict_types=1);

namespace App\Platform\Access\Contract;

use Symfony\Component\Uid\Uuid;

interface Administration
{
    /**
     * @param callable(): void $work
     */
    public function whileLocked(callable $work): void;

    public function isLastAdministrator(Uuid $accountId): bool;

    public function countRolesOf(Uuid $accountId): int;
}
