<?php

declare(strict_types=1);

namespace App\Workflow\Access\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\ValueObject\NamedAccounts;

final readonly class RoleHolder
{
    public function __construct(
        public string $id,
        public string $displayName,
        public string $username,
        public bool $disabled,
    ) {
    }

    public static function of(User $user, NamedAccounts $names): self
    {
        return new self(
            $user->id()->toRfc4122(),
            $names->nameOf($user),
            $user->username()->value,
            $user->status()->disabledAt !== null,
        );
    }
}
