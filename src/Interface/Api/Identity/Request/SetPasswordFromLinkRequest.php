<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

use SensitiveParameter;

final readonly class SetPasswordFromLinkRequest
{
    public function __construct(
        #[SensitiveParameter]
        public ?string $token = null,
        #[SensitiveParameter]
        public ?string $password = null,
    ) {
    }
}
