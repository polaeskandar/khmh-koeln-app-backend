<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class NewAccountDetails
{
    public function __construct(
        public string $username,
        public string $firstName,
        public string $lastName,
        public string $language,
    ) {
    }
}
