<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Repository\StaffAccountRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\StaffMember;
use App\Library\Collections\Model\Collections;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class StaffAccounts
{
    private const int LISTED = 500;

    public function __construct(
        private StaffAccountRepositoryInterface $accounts,
        private AccountNames $names,
    ) {
    }

    /**
     * @param list<Uuid> $staff the accounts with a staff role
     * @param list<Uuid> $holders the accounts with any role
     *
     * @return list<StaffMember> the staff and the accounts with no role that are not members;
     *         enabled first, each by name
     */
    public function listed(array $staff, array $holders): array
    {
        $rows = $this->accounts->listed($staff, $holders, self::LISTED);
        $names = $this->names->of($rows);
        $byName = AccountNames::sortKeys();

        return Collections::sortedBy(
            new ArrayCollection($rows)->map(
                static fn (array $row): StaffMember => new StaffMember(
                    $row['id'],
                    $names[$row['personId']] ?? '',
                    $row['username'],
                    $row['disabled'],
                ),
            ),
            keyOf: static fn (StaffMember $member): string => self::rankOf($member, $byName),
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
