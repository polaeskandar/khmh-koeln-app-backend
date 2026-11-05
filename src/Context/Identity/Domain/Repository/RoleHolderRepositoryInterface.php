<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\User;
use Doctrine\Common\Collections\ReadableCollection;

interface RoleHolderRepositoryInterface
{
    /**
     * @return ReadableCollection<int, User>
     */
    public function activeHolders(string $roleNameKey): ReadableCollection;
}
