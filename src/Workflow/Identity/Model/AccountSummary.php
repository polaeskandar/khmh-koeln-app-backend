<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\Account;

final readonly class AccountSummary
{
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
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
