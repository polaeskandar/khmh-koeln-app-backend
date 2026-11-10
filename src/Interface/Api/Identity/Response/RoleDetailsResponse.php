<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Context\Identity\Domain\Enum\RoleScope;
use App\Workflow\Identity\Model\RoleDetails;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Override;

final readonly class RoleDetailsResponse implements JsonSerializable
{
    public function __construct(
        private RoleDetails $details,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     scope: RoleScope,
     *     permissions: list<PermissionResponse>,
     *     createdAt: DateTimeImmutable,
     *     updatedAt: DateTimeImmutable,
     *     holders: list<RoleHolderResponse>,
     *     onlyWayToChangeRoles: bool,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $role = $this->details->role;

        return [
            'id' => $role->id,
            'name' => $role->name,
            'scope' => $this->details->scope,
            'permissions' => new ArrayCollection($role->permissions)->map(PermissionResponse::of(...))->getValues(),
            'createdAt' => $role->createdAt,
            'updatedAt' => $role->updatedAt,
            'holders' => new ArrayCollection($this->details->holders)->map(RoleHolderResponse::of(...))->getValues(),
            'onlyWayToChangeRoles' => $this->details->onlyWayToChangeRoles,
        ];
    }
}
