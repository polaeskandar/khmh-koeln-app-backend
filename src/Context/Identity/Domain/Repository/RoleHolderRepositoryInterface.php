<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use Doctrine\Common\Collections\ReadableCollection;

interface RoleHolderRepositoryInterface
{
    /**
     * @return ReadableCollection<int, User>
     */
    public function activeHolders(string $roleNameKey): ReadableCollection;

    /**
     * @return ReadableCollection<int, User>
     */
    public function holdersOf(Role $role): ReadableCollection;

    /**
     * @return array<string, int>
     */
    public function holderCounts(): array;

    public function hasActiveAccountThatCanWithout(Role $role, Action $action, Resource $resource): bool;
}
