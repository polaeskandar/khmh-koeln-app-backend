<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use SensitiveParameter;

final readonly class AccountChoice
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        #[SensitiveParameter]
        public string $password,
        public string $language,
    ) {
    }
}
