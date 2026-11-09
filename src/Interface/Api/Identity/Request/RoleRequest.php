<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RoleRequest
{
    /**
     * @param list<PermissionRequest> $permissions
     */
    public function __construct(
        public ?string $name = null,
        #[Assert\Valid]
        public array $permissions = [],
    ) {
    }

    /**
     * @return list<array{action: string|null, resource: string|null}>
     */
    public function permissionChoices(): array
    {
        return new ArrayCollection($this->permissions)->map(static fn (PermissionRequest $permission): array => [
            'action' => $permission->action,
            'resource' => $permission->resource,
        ])->getValues();
    }
}
