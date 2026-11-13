<?php

declare(strict_types=1);

namespace App\Context\Access\Application\Service;

use App\Context\Access\Domain\Entity\Role;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Access\Domain\Enum\RoleScope;
use App\Context\Access\Domain\Repository\RoleHolderRepositoryInterface;
use App\Context\Access\Domain\ValueObject\Grant;
use App\Library\Collections\Model\Collections;
use App\Platform\Access\Model\Group;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class RoleHolders
{
    public function __construct(
        private RoleHolderRepositoryInterface $holders,
        private GroupPermissions $groups,
    ) {
    }

    /**
     * @return list<Uuid> the accounts whose roles let them do it somewhere
     */
    public function thatCan(Action $action, Resource $resource): array
    {
        return $this->holders->holdersThatCan($action, $resource);
    }

    /**
     * @return list<Uuid> the accounts whose roles let them do it in the group
     */
    public function thatCanIn(Action $action, Resource $resource, Group $group): array
    {
        return $this->groups->listAllowedIn(
            $this->holders->holdersThatCan($action, $resource),
            $action,
            $resource,
            $group,
        );
    }

    /**
     * @return list<Uuid>
     */
    public function ofRoleKey(string $roleNameKey): array
    {
        return $this->holders->holdersOfKey($roleNameKey);
    }

    /**
     * @return list<Uuid> the accounts with a role that holds only in their assigned groups
     */
    public function servingInGroups(): array
    {
        return $this->holders->holdersIn(RoleScope::AssignedGroups);
    }

    /**
     * @param list<Uuid> $accountIds
     *
     * @return list<Uuid> those among them with a role that holds only in their assigned groups
     */
    public function servingInGroupsAmong(array $accountIds): array
    {
        $serving = new ArrayCollection($this->servingInGroups())->map(static fn (Uuid $id): string => $id->toRfc4122());

        return new ArrayCollection($accountIds)
            ->filter(static fn (Uuid $id): bool => $serving->contains($id->toRfc4122()))
            ->getValues();
    }

    /**
     * @return list<Uuid> the accounts with a staff role: one that grants anything outside the parent portal
     */
    public function staff(): array
    {
        return $this->holders->holdersBeyond(Resource::ParentPortal);
    }

    /**
     * @return list<Uuid> the accounts with a role that grants anything in the parent portal
     */
    public function parents(): array
    {
        return $this->holders->holdersOn(Resource::ParentPortal);
    }

    /**
     * @return list<Uuid> the accounts with any role
     */
    public function all(): array
    {
        return $this->holders->allHolders();
    }

    /**
     * @return list<Role> by name
     */
    public function listRolesOf(Uuid $accountId): array
    {
        return $this->holders->listRolesOf($accountId);
    }

    /**
     * @param list<Uuid> $accountIds
     *
     * @return array<string, list<Role>> each account's roles by name, by account id
     */
    public function listRolesOfAccounts(array $accountIds): array
    {
        return $this->holders->listRolesOfAccounts($accountIds);
    }

    /**
     * @return list<Grant> what the account's roles let it do, once each, in the order of the catalog
     */
    public function grantsOf(Uuid $accountId): array
    {
        return Collections::of(self::grants($this->holders->listRolesOf($accountId)))->getValues();
    }

    /**
     * @param list<Role> $roles
     *
     * @return iterable<int, Grant>
     */
    private static function grants(array $roles): iterable
    {
        foreach (Resource::cases() as $resource) {
            foreach (Action::cases() as $action) {
                $scope = PermissionScopes::of($roles, $action, $resource);

                if ($scope === null) {
                    continue;
                }

                yield new Grant($action, $resource, $scope);
            }
        }
    }
}
