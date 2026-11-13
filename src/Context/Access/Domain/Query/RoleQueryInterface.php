<?php

declare(strict_types=1);

namespace App\Context\Access\Domain\Query;

use App\Context\Access\Domain\Entity\Role;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface RoleQueryInterface
{
    public function find(Uuid $id): ?Role;

    /**
     * @return ReadableCollection<int, Role>
     */
    public function listByName(): ReadableCollection;
}
