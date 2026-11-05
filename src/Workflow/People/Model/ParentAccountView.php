<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\Identity\Domain\Entity\User;

final readonly class ParentAccountView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {
    }

    public static function of(User $account): self
    {
        $profile = $account->profile();

        return new self($account->id()->toRfc4122(), $profile->displayName->value, $profile->email->value);
    }
}
