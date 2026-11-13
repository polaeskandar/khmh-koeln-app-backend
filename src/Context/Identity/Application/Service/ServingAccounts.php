<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\RoleScope;
use App\Context\Identity\Domain\Repository\RoleHolderRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ServingAccounts
{
    public function __construct(
        private UserRepositoryInterface $users,
        private RoleHolderRepositoryInterface $holders,
        private AccountLookup $accounts,
    ) {
    }

    public function get(Uuid $id): User
    {
        return $this->users->find($id) ?? throw NotFound::of('user');
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return list<User> the active ones with a role that holds in assigned groups, by name
     */
    public function listActiveAmong(array $ids): array
    {
        return $this->byName(
            $this->users
                ->findByIds($ids)
                ->filter(
                    static fn (User $user): bool => $user->status()->isActive()
                    && !$user
                        ->roles()
                        ->filter(static fn (Role $role): bool => $role->scope() === RoleScope::AssignedGroups)
                        ->isEmpty(),
                ),
        );
    }

    /**
     * @return list<User> the active accounts with a role that holds in assigned groups, by name
     */
    public function listActive(): array
    {
        return $this->byName($this->holders->activeHoldersIn(RoleScope::AssignedGroups));
    }

    /**
     * @param ReadableCollection<int, User> $users
     *
     * @return list<User>
     */
    private function byName(ReadableCollection $users): array
    {
        return $this->accounts->named($users)->byName();
    }
}
