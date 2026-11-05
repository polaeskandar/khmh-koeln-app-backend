<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

interface StaffAccountRepositoryInterface
{
    /**
     * @param positive-int $limit
     *
     * @return list<array{id: string, displayName: string, email: string, disabled: bool}>
     */
    public function listed(int $limit): array;

    /**
     * @param list<string> $accountIds
     *
     * @return list<array{accountId: string, id: string, name: string}>
     */
    public function rolesOf(array $accountIds): array;
}
