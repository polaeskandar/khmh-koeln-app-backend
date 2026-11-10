<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Query;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use Doctrine\Common\Collections\ReadableCollection;

interface RoleHolderQueryInterface
{
    /**
     * @return ReadableCollection<int, User>
     */
    public function listHoldersOf(Role $role): ReadableCollection;

    /**
     * @return array<string, int>
     */
    public function countHolders(): array;
}
