<?php

declare(strict_types=1);

namespace App\Context\Access\Domain\Repository;

use App\Context\Access\Domain\Entity\Role;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Access\Domain\Enum\RoleScope;
use App\Context\Access\Domain\Query\RoleHolderQueryInterface;
use Symfony\Component\Uid\Uuid;

interface RoleHolderRepositoryInterface extends RoleHolderQueryInterface
{
    /**
     * @return list<Uuid> the accounts with the role of that key
     */
    public function holdersOfKey(string $roleNameKey): array;

    /**
     * @return list<Uuid> the accounts with a role of that scope
     */
    public function holdersIn(RoleScope $scope): array;

    /**
     * @return list<Uuid> the accounts with a role that grants it, leaving out what they hold through $without
     */
    public function holdersThatCan(Action $action, Resource $resource, ?Role $without = null): array;

    /**
     * @return list<Uuid> the accounts with a role that grants anything on the resource
     */
    public function holdersOn(Resource $resource): array;

    /**
     * @return list<Uuid> the accounts with a role that grants anything on another resource
     */
    public function holdersBeyond(Resource $resource): array;

    /**
     * @return list<Uuid> the accounts with any role
     */
    public function allHolders(): array;
}
