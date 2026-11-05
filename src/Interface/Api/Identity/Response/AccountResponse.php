<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\AccountSummary;

final readonly class AccountResponse
{
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $language,
    ) {
    }

    public static function of(AccountSummary $account): self
    {
        return new self($account->id, $account->email, $account->displayName, $account->language);
    }
}
