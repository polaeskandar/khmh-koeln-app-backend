<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use Symfony\Component\Uid\Uuid;

interface MemberAccountRepositoryInterface
{
    /**
     * @param list<Uuid> $personIds
     *
     * @return array<string, string> the enabled member account of each of those people, by person id
     */
    public function activeAccountsOf(array $personIds): array;

    /**
     * @return list<array{
     *     id: string,
     *     username: string,
     *     disabled: bool,
     *     isParent: bool,
     *     personId: string,
     *     ageCheck: string|null,
     *     guardianConsentNote: string|null,
     * }> the linked accounts without a staff role, by username
     */
    public function listed(): array;
}
