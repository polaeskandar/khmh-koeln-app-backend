<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Repository\MemberAccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\RoleHolderRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Collections\Model\Collections;
use Collator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AccountLookup
{
    private const string COLLATION = 'de';

    public function __construct(
        private UserRepositoryInterface $users,
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
     * @return ReadableCollection<int, User>
     */
    public function activeHoldersOf(string $roleNameKey): ReadableCollection
    {
        return $this->roleHolders->activeHolders($roleNameKey);
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, User>
     */
    public function accounts(array $ids): ReadableCollection
    {
        return $this->users->findByIds($ids);
    }

    public function account(?Uuid $id): ?User
    {
        return $id === null ? null : $this->users->find($id);
    }

    /**
     * @return list<User>
     */
    public function activeAllowedTo(Action $action, Resource $resource): array
    {
        $collator = new Collator(self::COLLATION);

        $nameKeyOf =
            static fn (User $user): string => (string) $collator->getSortKey($user->profile()->displayName->value);

        return Collections::sortedBy($this->users->activeThatCan($action, $resource), keyOf: $nameKeyOf);
    }

    public function isActiveAndAllowedTo(Uuid $accountId, Action $action, Resource $resource): bool
    {
        $user = $this->users->find($accountId);

        return $user !== null && RolePermissions::activeAccountHas($user, $action, $resource);
    }
}
