<?php

declare(strict_types=1);

namespace App\Workflow\Access\Model;

use App\Context\Access\Domain\Entity\Role;
use App\Context\Access\Domain\ValueObject\Access;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class RoleDefinition
{
    /**
     * @param list<PermissionGrant> $permissions
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $permissions,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param list<Access> $grants
     */
    public static function of(Role $role, array $grants): self
    {
        return new self(
            id: $role->id()->toRfc4122(),
            name: $role->name()->value,
            permissions: new ArrayCollection($grants)->map(PermissionGrant::of(...))->getValues(),
            createdAt: $role->createdAt(),
            updatedAt: $role->updatedAt(),
        );
    }
}
