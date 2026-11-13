<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Context\Identity\Domain\Enum\Language;
use App\Workflow\Identity\Model\AccountSummary;

final readonly class AccountResponse
{
    public function __construct(
        public string $id,
        public string $username,
        public string $displayName,
        public Language $language,
    ) {
    }

    public static function of(AccountSummary $account): self
    {
        return new self($account->id, $account->username, $account->displayName, $account->language);
    }
}
