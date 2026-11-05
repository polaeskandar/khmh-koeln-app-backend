<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;

final readonly class LastAdministrator
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function is(User $user): bool
    {
        $administers = $user->status()->isActive() && $user->can(Action::Update, Resource::Roles);
        $othersDo = $this->users->hasOtherActiveAccountThatCan($user, Action::Update, Resource::Roles);

        return $administers && !$othersDo;
    }

    public function wouldLoseIt(User $user, Role $role): bool
    {
        $roleAdministers = $role->allows(Action::Update, Resource::Roles);
        $keepsIt = $user->can(Action::Update, Resource::Roles, without: $role);

        return $roleAdministers && !$keepsIt && $this->is($user);
    }
}
