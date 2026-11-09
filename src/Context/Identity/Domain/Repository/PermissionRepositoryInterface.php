<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\Permission;
use Doctrine\Common\Collections\ReadableCollection;

interface PermissionRepositoryInterface
{
    /**
     * @return ReadableCollection<int, Permission>
     */
    public function catalog(): ReadableCollection;
}
