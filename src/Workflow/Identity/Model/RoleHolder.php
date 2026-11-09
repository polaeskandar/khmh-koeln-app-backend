<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\User;

final readonly class RoleHolder
{
    public function __construct(
        public string $id,
        public string $displayName,
        public string $email,
        public bool $disabled,
    ) {
    }

    public static function of(User $user): self
    {
        $profile = $user->profile();

        return new self(
            $user->id()->toRfc4122(),
            $profile->displayName->value,
            $profile->email->value,
            $user->status()->disabledAt !== null,
        );
    }
}
