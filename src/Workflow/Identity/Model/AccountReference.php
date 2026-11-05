<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\User;

final readonly class AccountReference
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }

    public static function of(User $user): self
    {
        return new self($user->id()->toRfc4122(), $user->profile()->displayName->value);
    }
}
