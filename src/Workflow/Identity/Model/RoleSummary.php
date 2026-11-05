<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\Role;

final readonly class RoleSummary
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }

    public static function of(Role $role): self
    {
        return new self($role->id()->toRfc4122(), $role->name()->value);
    }
}
