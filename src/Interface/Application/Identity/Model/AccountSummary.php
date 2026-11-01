<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\Model;

final readonly class AccountSummary
{
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $language,
    ) {
    }
}
