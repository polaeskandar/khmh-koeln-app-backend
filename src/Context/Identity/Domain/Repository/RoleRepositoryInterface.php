<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Query\RoleQueryInterface;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface RoleRepositoryInterface extends RoleQueryInterface
{
    public function save(Role $role): void;

    public function remove(Role $role): void;

    public function findByName(string $name): ?Role;

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Role>
     */
    public function findByIds(array $ids): ReadableCollection;
}
