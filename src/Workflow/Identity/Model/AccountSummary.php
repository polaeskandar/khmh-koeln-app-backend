<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\User;

final readonly class AccountSummary
{
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $language,
    ) {
    }

    public static function of(User $user): self
    {
        $profile = $user->profile();

        return new self(
            $user->id()->toRfc4122(),
            $profile->email->value,
            $profile->displayName->value,
            $profile->language->value,
        );
    }
}
