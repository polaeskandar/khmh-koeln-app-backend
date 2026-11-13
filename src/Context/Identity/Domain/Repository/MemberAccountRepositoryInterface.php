<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\User;
use Symfony\Component\Uid\Uuid;

interface MemberAccountRepositoryInterface
{
    public function holderOf(Uuid $personId): ?User;

    /**
     * @param list<Uuid> $personIds
     *
     * @return array<string, string> the approved, enabled account linked to each of those records, by person id
     */
    public function activeAccountsOf(array $personIds): array;

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     email: string,
     *     disabled: bool,
     *     isParent: bool,
     *     personId: string,
     *     ageCheck: string|null,
     *     guardianConsentNote: string|null,
     * }> the linked accounts without a staff role, by name
     */
    public function listed(): array;
}
