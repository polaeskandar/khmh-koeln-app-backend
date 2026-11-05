<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

use App\Context\Identity\Domain\Entity\User;

final readonly class GroupServant
{
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
    ) {
    }

    public static function of(User $account, string $role): self
    {
        return new self($account->id()->toRfc4122(), $account->profile()->displayName->value, $role);
    }
}
