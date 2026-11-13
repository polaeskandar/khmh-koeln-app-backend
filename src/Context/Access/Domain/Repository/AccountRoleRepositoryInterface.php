<?php

declare(strict_types=1);

namespace App\Context\Access\Domain\Repository;

use App\Context\Access\Domain\Entity\Role;
use Symfony\Component\Uid\Uuid;

interface AccountRoleRepositoryInterface
{
    public function give(Uuid $accountId, Role $role): void;

    public function take(Uuid $accountId, Role $role): void;

    public function forget(Uuid $accountId): int;
}
