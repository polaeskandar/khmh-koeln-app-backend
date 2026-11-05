<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\RoleSummary;

final readonly class RoleResponse
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }

    public static function of(RoleSummary $role): self
    {
        return new self($role->id, $role->name);
    }
}
