<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\Account;

final readonly class AccountIdentity
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public string $language,
    ) {
    }

    public static function of(Account $account): self
    {
        $profile = $account->profile();

        return new self(
            $account->id()->toRfc4122(),
            $profile->email->value,
            $profile->displayName->value,
            $profile->language->value,
        );
    }
}
