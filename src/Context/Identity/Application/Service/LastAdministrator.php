<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Repository\RoleHolderRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;

final readonly class LastAdministrator
{
    public function __construct(
        private UserRepositoryInterface $users,
        private RoleHolderRepositoryInterface $holders,
    ) {
    }

    public function isKeptOnlyByAccount(User $user): bool
    {
        $administers = RolePermissions::activeAccountHas($user, Action::Update, Resource::Roles);

        $othersDo = $this->users->hasOtherActiveAccountThatCan($user, Action::Update, Resource::Roles);

        return $administers && !$othersDo;
    }

    public function wouldLoseIt(User $user, Role $role): bool
    {
        $roleAdministers = RolePermissions::roleAllows($role, Action::Update, Resource::Roles);
        $keepsIt = RolePermissions::accountHasWithout($user, $role, Action::Update, Resource::Roles);

        return $roleAdministers && !$keepsIt && $this->isKeptOnlyByAccount($user);
    }

    public function isKeptOnlyByRole(Role $role): bool
    {
        $roleAdministers = RolePermissions::roleAllows($role, Action::Update, Resource::Roles);
        $othersDo = $this->holders->hasActiveAccountThatCanWithout($role, Action::Update, Resource::Roles);

        return $roleAdministers && !$othersDo;
    }
}
