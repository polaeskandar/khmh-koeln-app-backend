<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Language;

final readonly class AccountIdentity
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public Language $language,
    ) {
    }

    public static function of(User $user): self
    {
        $profile = $user->profile();

        return new self(
            $user->id()->toRfc4122(),
            $profile->email->value,
            $profile->displayName->value,
            $profile->language,
        );
    }
}
