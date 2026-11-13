<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Repository\StaffAccountRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\HeldRole;
use App\Context\Identity\Domain\ValueObject\StaffMember;
use App\Library\Collections\Model\Collections;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class StaffAccounts
{
    private const int LISTED = 500;

    public function __construct(
        private StaffAccountRepositoryInterface $accounts,
        private AccountNames $names,
    ) {
    }

    /**
     * @return list<StaffMember> enabled first, each by name
     */
    public function listed(): array
    {
        $rows = $this->accounts->listed(self::LISTED);
        $accounts = new ArrayCollection($rows);
        $names = $this->names->of($rows);
        $byName = AccountNames::sortKeys();

        $roles = new ArrayCollection(
            $this->accounts->rolesOf($accounts->map(static fn (array $row): string => $row['id'])->getValues()),
        );

        return Collections::sortedBy(
            $accounts->map(static fn (array $row): StaffMember => self::memberOf($row, $names, $roles)),
            keyOf: static fn (StaffMember $member): string => self::rankOf($member, $byName),
        );
    }

    /**
     * @param array{id: string, username: string, personId: string, disabled: bool} $row
     * @param array<string, string> $names
     * @param ArrayCollection<int, array{accountId: string, id: string, name: string}> $roles
     */
    private static function memberOf(array $row, array $names, ArrayCollection $roles): StaffMember
    {
        return new StaffMember(
            $row['id'],
            $names[$row['personId']] ?? '',
            $row['username'],
            $row['disabled'],
            $roles
                ->filter(static fn (array $role): bool => $role['accountId'] === $row['id'])
                ->map(static fn (array $role): HeldRole => new HeldRole($role['id'], $role['name']))
                ->getValues(),
        );
    }

    /**
     * @param Closure(string): string $byName
     */
    private static function rankOf(StaffMember $member, Closure $byName): string
    {
        $disabled = $member->disabled ? '1' : '0';

        return $disabled . $byName($member->displayName);
    }
}
