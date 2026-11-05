<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\Access;

final readonly class LastAdministrator
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AdministrationLockInterface $administration,
    ) {
    }

    /**
     * @param callable(): void $work
     */
    public function whileLocked(callable $work): void
    {
        $this->administration->whileLocked($work);
    }

    public function is(User $user): bool
    {
        $administers = self::administration()->isAllowedFor($user);
        $othersDo = $this->users->hasOtherActiveAccountThatCan($user, Action::Update, Resource::Roles);

        return $administers && !$othersDo;
    }

    public function wouldLoseIt(User $user, Role $role): bool
    {
        $roleAdministers = $role->allows(Action::Update, Resource::Roles);
        $keepsIt = self::administration()->isHeldBy($user, without: $role);

        return $roleAdministers && !$keepsIt && $this->is($user);
    }

    private static function administration(): Access
    {
        return new Access(Action::Update, Resource::Roles);
    }
}
