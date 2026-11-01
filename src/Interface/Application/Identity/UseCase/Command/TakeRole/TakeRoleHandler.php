<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\UseCase\Command\TakeRole;

use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\Service\AdministrationLockInterface;
use App\Context\Identity\Domain\Service\RoleAssignments;
use App\Library\Problem\Exception\NotFound;

final readonly class TakeRoleHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private RoleAssignments $assignments,
        private AdministrationLockInterface $administration,
    ) {
    }

    public function __invoke(TakeRoleCommand $command): void
    {
        $this->administration->whileLocked(function () use ($command): void {
            $user = $this->users->findByEmail($command->email) ?? throw NotFound::of('account');
            $role = $this->roles->findByName($command->role) ?? throw NotFound::of('role');

            $this->assignments->take($user, $role);
        });
    }
}
