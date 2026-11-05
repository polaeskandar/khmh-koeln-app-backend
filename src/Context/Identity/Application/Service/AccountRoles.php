<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Interface\AdministrationLockInterface;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\RoleChange;
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

        RoleChange::ensureGivable($user, $role);
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

            RoleChange::ensureHeld($user, $role);
            $user->unassign($role);
            $this->users->save($user);
        });
    }

    private function account(string $id): User
    {
        $user = Uuid::isValid($id) ? $this->users->find(Uuid::fromString($id)) : null;

        return $user ?? throw NotFound::of('user');
    }

    private function role(string $id): ?Role
    {
        $roles = Uuid::isValid($id) ? $this->roles->findByIds([Uuid::fromString($id)])->getValues() : [];

        return $roles[0] ?? null;
    }
}
