<?php

declare(strict_types=1);

namespace App\Context\Access\Domain\Query;

use App\Context\Access\Domain\Entity\Role;
use Symfony\Component\Uid\Uuid;

interface RoleHolderQueryInterface
{
    /**
     * @return list<Uuid> the accounts that hold the role
     */
    public function listHoldersOf(Role $role): array;

    /**
     * @return array<string, int>
     */
    public function countHolders(): array;

    /**
     * @return list<Role> the roles the account holds, by name
     */
    public function listRolesOf(Uuid $accountId): array;

    /**
     * @param list<Uuid> $accountIds
     *
     * @return array<string, list<Role>> each account's roles by name, by account id; accounts without one are left out
     */
    public function listRolesOfAccounts(array $accountIds): array;
}
