<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Repository\StaffAccountRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\HeldRole;
use App\Context\Identity\Domain\ValueObject\StaffMember;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class StaffAccounts
{
    private const int LISTED = 500;

    public function __construct(
        private StaffAccountRepositoryInterface $accounts,
    ) {
    }

    /**
     * @return list<StaffMember>
     */
    public function listed(): array
    {
        $accounts = new ArrayCollection($this->accounts->listed(self::LISTED));

        $roles = new ArrayCollection(
            $this->accounts->rolesOf($accounts->map(static fn (array $row): string => $row['id'])->getValues()),
        );

        return $accounts->map(
            static fn (array $row): StaffMember => new StaffMember(
                $row['id'],
                $row['displayName'],
                $row['email'],
                $row['disabled'],
                $roles
                    ->filter(static fn (array $role): bool => $role['accountId'] === $row['id'])
                    ->map(static fn (array $role): HeldRole => new HeldRole($role['id'], $role['name']))
                    ->getValues(),
            ),
        )->getValues();
    }
}
