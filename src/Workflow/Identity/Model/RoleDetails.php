<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\RoleScope;
use App\Context\Identity\Domain\ValueObject\Access;
use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class RoleDetails
{
    /**
     * @param list<RoleHolder> $holders
     */
    public function __construct(
        public RoleDefinition $role,
        public RoleScope $scope,
        public array $holders,
        public bool $onlyWayToChangeRoles,
    ) {
    }

    /**
     * @param list<Access> $grants
     */
    public static function of(Role $role, array $grants, NamedAccounts $holders, bool $onlyWayToChangeRoles): self
    {
        return new self(
            RoleDefinition::of($role, $grants),
            $role->scope(),
            new ArrayCollection($holders->byName())->map(
                static fn (User $holder): RoleHolder => RoleHolder::of($holder, $holders),
            )->getValues(),
            $onlyWayToChangeRoles,
        );
    }
}
