<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\Role;

interface RoleRepositoryInterface
{
    public function save(Role $role): void;

    public function findByName(string $name): ?Role;
}
