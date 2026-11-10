<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Enum\RoleScope;
use App\Context\Identity\Domain\Interface\SignedInAccountInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Platform\Access\Contract\StaffGroups;
use App\Platform\Access\Model\Group;
use App\Platform\Access\Model\GroupScope;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class GroupPermissions
{
    public function __construct(
        private SignedInAccountInterface $signedIn,
        private UserRepositoryInterface $users,
        private StaffGroups $groups,
    ) {
    }

    public function getViewerScope(Action $action, Resource $resource): GroupScope
    {
        $id = $this->signedIn->id();
        $viewer = $id === null ? null : $this->users->find($id);

        return $viewer === null ? GroupScope::assigned([]) : $this->getScopeOf($viewer, $action, $resource);
    }

    public function getScopeOf(User $user, Action $action, Resource $resource): GroupScope
    {
        return match ($user->status()->isActive() ? PermissionScopes::of($user, $action, $resource) : null) {
            RoleScope::Church => GroupScope::churchWide(),
            RoleScope::AssignedGroups => GroupScope::assigned($this->groups->of($user->id())),
            null => GroupScope::assigned([]),
        };
    }

    /**
     * @param ReadableCollection<int, User> $users
     *
     * @return ReadableCollection<int, User> those whose roles let them do it in the group
     */
    public function listAllowedIn(
        ReadableCollection $users,
        Action $action,
        Resource $resource,
        Group $group,
    ): ReadableCollection {
        $covering = new ArrayCollection($this->groups->coveringAccounts($group->serviceId, $group->stageId))->map(
            static fn (Uuid $id): string => $id->toRfc4122(),
        );

        return $users->filter(static fn (User $user): bool => match (PermissionScopes::of($user, $action, $resource)) {
            RoleScope::Church => true,
            RoleScope::AssignedGroups => $covering->contains($user->id()->toRfc4122()),
            null => false,
        });
    }
}
