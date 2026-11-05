<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\OwnAdministration;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Symfony\Component\Uid\Uuid;

final readonly class AccountRoles
{
    public function __construct(
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private LastAdministrator $lastAdministrator,
        private AdministrationLockInterface $administration,
    ) {
    }

    public function give(string $accountId, string $roleId): Role
    {
        $user = $this->account($accountId);
        $role = $this->role($roleId) ?? throw InvalidInput::field('roleId', 'roles.unknown');

        if ($user->status()->isPending()) {
            throw new RuleViolation('roles.accountPending');
        }

        $user->assign($role);
        $this->users->save($user);

        return $role;
    }

    public function take(string $accountId, string $roleId, string $actorId): void
    {
        $this->administration->whileLocked(function () use ($accountId, $roleId, $actorId): void {
            $user = $this->account($accountId);
            $role = $this->role($roleId) ?? throw NotFound::of('role');
            OwnAdministration::ensureKept($user, $role, Uuid::fromString($actorId));

            if ($this->lastAdministrator->wouldLoseIt($user, $role)) {
                throw new RuleViolation('roles.lastAdministrator');
            }

            $user->unassign($role);
            $this->users->save($user);
        });
    }

    private function account(string $id): User
    {
        return $this->users->find(Uuid::fromString($id)) ?? throw NotFound::of('user');
    }

    private function role(string $id): ?Role
    {
        return $this->roles->findByIds([Uuid::fromString($id)])->findFirst(static fn (): bool => true);
    }
}
