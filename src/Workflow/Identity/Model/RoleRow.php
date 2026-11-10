<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Enum\RoleScope;
use App\Context\Identity\Domain\ValueObject\Access;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class RoleRow
{
    /**
     * @param list<PermissionGrant> $permissions
     */
    public function __construct(
        public string $id,
        public string $name,
        public RoleScope $scope,
        public array $permissions,
        public int $holderCount,
    ) {
    }

    /**
     * @param list<Access> $grants
     */
    public static function of(Role $role, array $grants, int $holderCount): self
    {
        return new self(
            $role->id()->toRfc4122(),
            $role->name()->value,
            $role->scope(),
            new ArrayCollection($grants)->map(PermissionGrant::of(...))->getValues(),
            $holderCount,
        );
    }
}
