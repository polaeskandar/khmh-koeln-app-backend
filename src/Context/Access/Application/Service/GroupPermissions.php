<?php

declare(strict_types=1);

namespace App\Context\Access\Application\Service;

use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Access\Domain\Enum\RoleScope;
use App\Context\Access\Domain\Interface\ViewerInterface;
use App\Context\Access\Domain\Repository\RoleHolderRepositoryInterface;
use App\Platform\Access\Contract\ActiveAccounts;
use App\Platform\Access\Contract\StaffGroups;
use App\Platform\Access\Model\Group;
use App\Platform\Access\Model\GroupScope;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class GroupPermissions
{
    public function __construct(
        private ViewerInterface $viewer,
        private RoleHolderRepositoryInterface $holders,
        private ActiveAccounts $accounts,
        private StaffGroups $groups,
    ) {
    }

    public function getViewerScope(Action $action, Resource $resource): GroupScope
    {
        $id = $this->viewer->id();

        return $id === null ? GroupScope::assigned([]) : $this->getScopeOf($id, $action, $resource);
    }

    public function getScopeOf(Uuid $accountId, Action $action, Resource $resource): GroupScope
    {
        $scope = $this->accounts->isEnabled($accountId)
            ? PermissionScopes::of($this->holders->listRolesOf($accountId), $action, $resource)
            : null;

        return match ($scope) {
            RoleScope::Church => GroupScope::churchWide(),
            RoleScope::AssignedGroups => GroupScope::assigned($this->groups->of($accountId)),
            null => GroupScope::assigned([]),
        };
    }

    /**
     * @param list<Uuid> $accountIds
     *
     * @return list<Uuid> those whose roles let them do it in the group
     */
    public function listAllowedIn(array $accountIds, Action $action, Resource $resource, Group $group): array
    {
        $roles = $this->holders->listRolesOfAccounts($accountIds);

        $covering = new ArrayCollection($this->groups->coveringAccounts($group->serviceId, $group->stageId))->map(
            static fn (Uuid $id): string => $id->toRfc4122(),
        );

        return new ArrayCollection($accountIds)
            ->filter(static fn (Uuid $id): bool => match (PermissionScopes::of(
                $roles[$id->toRfc4122()] ?? [],
                $action,
                $resource,
            )) {
                RoleScope::Church => true,
                RoleScope::AssignedGroups => $covering->contains($id->toRfc4122()),
                null => false,
            })
            ->getValues();
    }
}
