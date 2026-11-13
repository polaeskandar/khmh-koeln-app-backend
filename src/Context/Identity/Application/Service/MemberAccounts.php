<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Repository\MemberAccountRepositoryInterface;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MemberAccounts
{
    public function __construct(
        private MemberAccountRepositoryInterface $members,
        private AccountNames $names,
    ) {
    }

    /**
     * @param list<Uuid> $staff the accounts with a staff role
     * @param list<Uuid> $parents the accounts with a role in the parent portal
     *
     * @return list<array{
     *     id: string,
     *     name: string,
     *     username: string,
     *     disabled: bool,
     *     isParent: bool,
     *     personId: string,
     *     ageCheck: string|null,
     *     guardianConsentNote: string|null,
     * }> the linked accounts without a staff role, by name
     */
    public function listed(array $staff, array $parents): array
    {
        $rows = $this->members->listed($staff, $parents);
        $names = $this->names->of($rows);
        $sortKeyOf = AccountNames::sortKeys();

        return Collections::sortedBy(
            new ArrayCollection($rows)->map(static fn (array $row): array => [
                'name' => $names[$row['personId']] ?? '',
                ...$row,
            ]),
            keyOf: static fn (array $row): string => $sortKeyOf($row['name']),
        );
    }
}
