<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\MemberAccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\RoleHolderRepositoryInterface;
use App\Library\Collections\Model\Collections;
use Collator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AccountLookup
{
    private const string COLLATION = 'de';

    public function __construct(
        private AccountRepositoryInterface $accounts,
        private RoleHolderRepositoryInterface $roleHolders,
        private MemberAccountRepositoryInterface $members,
    ) {
    }

    /**
     * @param list<Uuid> $personIds
     *
     * @return ReadableCollection<string, string> the approved, enabled account linked to each person's own record
     */
    public function ownAccountsOf(array $personIds): ReadableCollection
    {
        return new ArrayCollection($this->members->activeAccountsOf($personIds));
    }

    /**
     * @return ReadableCollection<int, Account>
     */
    public function activeHoldersOf(string $roleNameKey): ReadableCollection
    {
        return $this->roleHolders->activeHolders($roleNameKey);
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Account>
     */
    public function accounts(array $ids): ReadableCollection
    {
        return $this->accounts->findByIds($ids);
    }

    public function find(?Uuid $id): ?Account
    {
        return $id === null ? null : $this->accounts->find($id);
    }

    /**
     * @return list<Account>
     */
    public function activeAllowedTo(Action $action, Resource $resource): array
    {
        $collator = new Collator(self::COLLATION);

        $nameKeyOf = static fn (Account $account): string => (string) $collator->getSortKey(self::displayNameOf(
            $account,
        ));

        return Collections::sortedBy($this->accounts->activeThatCan($action, $resource), keyOf: $nameKeyOf);
    }

    public function isActiveAndAllowedTo(Uuid $accountId, Action $action, Resource $resource): bool
    {
        $account = $this->accounts->find($accountId);

        return $account !== null && RolePermissions::activeAccountHas($account, $action, $resource);
    }

    private static function displayNameOf(Account $account): string
    {
        return $account->profile()->displayName->value;
    }
}
