<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\RoleRow;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class RoleRowResponse
{
    /**
     * @param list<PermissionResponse> $permissions
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $permissions,
        public int $holderCount,
    ) {
    }

    public static function of(RoleRow $role): self
    {
        return new self(
            $role->id,
            $role->name,
            new ArrayCollection($role->permissions)->map(PermissionResponse::of(...))->getValues(),
            $role->holderCount,
        );
    }
}
