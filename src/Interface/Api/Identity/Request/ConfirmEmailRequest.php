<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

use SensitiveParameter;

final readonly class ConfirmEmailRequest
{
    public function __construct(
        #[SensitiveParameter]
        public ?string $token = null,
    ) {
    }
}
