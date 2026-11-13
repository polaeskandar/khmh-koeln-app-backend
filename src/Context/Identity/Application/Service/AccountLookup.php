<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Repository\MemberAccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\RoleHolderRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use App\Library\Collections\Model\Collections;
use App\Platform\Access\Model\Group;
use App\Platform\Directory\Contract\PersonNames;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AccountLookup
{
    public function __construct(
        private UserRepositoryInterface $users,
        private RoleHolderRepositoryInterface $roleHolders,
        private MemberAccountRepositoryInterface $members,
        private GroupPermissions $groups,
        private PersonNames $people,
    ) {
    }

    /**
     * @param ReadableCollection<int, User> $accounts
     */
    public function named(ReadableCollection $accounts): NamedAccounts
    {
        return NamedAccounts::of(
            $accounts,
            $this->people->namesOf(
                $accounts->map(static fn (User $account): Uuid => $account->personId())->getValues(),
            ),
        );
    }

    /**
     * @param list<?Uuid> $ids
     */
    public function namedByIds(array $ids): NamedAccounts
    {
        $present = Collections::mapPresent(new ArrayCollection($ids), static fn (?Uuid $id): ?Uuid => $id);

        return $this->named($this->users->findByIds($present->getValues()));
    }

    public function nameOf(User $account): string
    {
        return $this->named(new ArrayCollection([$account]))->nameOf($account);
    }

    /**
     * @param list<Uuid> $personIds
     *
     * @return ReadableCollection<string, string> the approved, enabled account linked to each person's own record
     */
    public function listOwnAccountsOf(array $personIds): ReadableCollection
    {
        return new ArrayCollection($this->members->activeAccountsOf($personIds));
    }

    /**
     * @return ReadableCollection<int, User>
     */
    public function listActiveHoldersOf(string $roleNameKey): ReadableCollection
    {
        return $this->roleHolders->activeHolders($roleNameKey);
    }

    public function findAccount(?Uuid $id): ?User
    {
        return $id === null ? null : $this->users->find($id);
    }

    /**
     * @return list<User> by name
     */
    public function listActiveAllowedTo(Action $action, Resource $resource): array
    {
        return $this->named($this->users->activeThatCan($action, $resource))->byName();
    }

    /**
     * @return list<User> by name
     */
    public function listActiveAllowedIn(Action $action, Resource $resource, Group $group): array
    {
        $allowed = $this->groups->listAllowedIn(
            $this->users->activeThatCan($action, $resource),
            $action,
            $resource,
            $group,
        );

        return new ArrayCollection($this->listActiveAllowedTo($action, $resource))
            ->filter($allowed->contains(...))
            ->getValues();
    }
}
