<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\Account;
use Doctrine\Common\Collections\ReadableCollection;

interface RoleHolderRepositoryInterface
{
    /**
     * @return ReadableCollection<int, Account>
     */
    public function activeHolders(string $roleNameKey): ReadableCollection;
}
