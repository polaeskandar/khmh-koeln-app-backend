<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use Symfony\Component\Uid\Uuid;

interface StaffAccountRepositoryInterface
{
    /**
     * @param list<Uuid> $staff the accounts with a staff role
     * @param list<Uuid> $holders the accounts with any role
     * @param positive-int $limit
     *
     * @return list<array{id: string, username: string, personId: string, disabled: bool}> the staff, and the accounts
     *         with no role that are not members; enabled first, by username
     */
    public function listed(array $staff, array $holders, int $limit): array;
}
