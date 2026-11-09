<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;

final readonly class LastAdministrator
{
    public function __construct(
        private AccountRepositoryInterface $accounts,
    ) {
    }

    public function is(Account $account): bool
    {
        $administers = RolePermissions::activeAccountHas($account, Action::Update, Resource::Roles);

        $othersDo = $this->accounts->hasOtherActiveAccountThatCan($account, Action::Update, Resource::Roles);

        return $administers && !$othersDo;
    }

    public function wouldLoseIt(Account $account, Role $role): bool
    {
        $roleAdministers = RolePermissions::roleAllows($role, Action::Update, Resource::Roles);
        $keepsIt = RolePermissions::accountHasWithout($account, $role, Action::Update, Resource::Roles);

        return $roleAdministers && !$keepsIt && $this->is($account);
    }
}
