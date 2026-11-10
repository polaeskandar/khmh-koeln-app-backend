<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Enum\RoleScope;
use App\Context\Identity\Domain\Query\RoleHolderQueryInterface;
use Doctrine\Common\Collections\ReadableCollection;

interface RoleHolderRepositoryInterface extends RoleHolderQueryInterface
{
    /**
     * @return ReadableCollection<int, User>
     */
    public function activeHolders(string $roleNameKey): ReadableCollection;

    /**
     * @return ReadableCollection<int, User> the approved, enabled accounts with a role of that scope
     */
    public function activeHoldersIn(RoleScope $scope): ReadableCollection;

    public function hasActiveAccountThatCanWithout(Role $role, Action $action, Resource $resource): bool;
}
