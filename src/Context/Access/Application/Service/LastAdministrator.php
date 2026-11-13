<?php

declare(strict_types=1);

namespace App\Context\Access\Application\Service;

use App\Context\Access\Domain\Entity\Role;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Access\Domain\Interface\AdministrationLockInterface;
use App\Context\Access\Domain\Repository\RoleHolderRepositoryInterface;
use App\Platform\Access\Contract\ActiveAccounts;
use App\Platform\Access\Contract\Administration;
use Doctrine\Common\Collections\ArrayCollection;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class LastAdministrator implements Administration
{
    public function __construct(
        private RoleHolderRepositoryInterface $holders,
        private ActiveAccounts $accounts,
        private AdministrationLockInterface $administration,
    ) {
    }

    #[Override]
    public function whileLocked(callable $work): void
    {
        $this->administration->whileLocked($work);
    }

    #[Override]
    public function isLastAdministrator(Uuid $accountId): bool
    {
        $administers =
            $this->accounts->isEnabled($accountId)
            && RolePermissions::isGrantedToAny(
                $this->holders->listRolesOf($accountId),
                Action::Update,
                Resource::Roles,
            );

        $others = new ArrayCollection($this->holders->holdersThatCan(
            Action::Update,
            Resource::Roles,
        ))->filter(static fn (Uuid $holder): bool => !$holder->equals($accountId));

        return $administers && $this->accounts->remainingAmong($others->getValues()) === [];
    }

    #[Override]
    public function countRolesOf(Uuid $accountId): int
    {
        return new ArrayCollection($this->holders->listRolesOf($accountId))->count();
    }

    public function isLostWithout(Uuid $accountId, Role $role): bool
    {
        $roleAdministers = RolePermissions::isGrantedToRole($role, Action::Update, Resource::Roles);

        $keepsIt = RolePermissions::isGrantedWithout(
            $this->holders->listRolesOf($accountId),
            $role,
            Action::Update,
            Resource::Roles,
        );

        return $roleAdministers && !$keepsIt && $this->isLastAdministrator($accountId);
    }

    public function isKeptOnlyByRole(Role $role): bool
    {
        $roleAdministers = RolePermissions::isGrantedToRole($role, Action::Update, Resource::Roles);
        $others = $this->holders->holdersThatCan(Action::Update, Resource::Roles, without: $role);

        return $roleAdministers && $this->accounts->remainingAmong($others) === [];
    }
}
