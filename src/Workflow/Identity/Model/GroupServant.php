<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Workflow\Organization\Model\AssignedGroup;

final readonly class GroupServant
{
    /**
     * @param list<AssignedGroup> $groups
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
        public array $groups,
    ) {
    }

    public static function of(User $account, string $name, string $role, ServantGroups $groups): self
    {
        return new self($account->id()->toRfc4122(), $name, $role, $groups->servedBy($account));
    }
}
