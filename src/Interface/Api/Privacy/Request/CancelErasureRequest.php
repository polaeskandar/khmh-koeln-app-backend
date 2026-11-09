<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Request;

use SensitiveParameter;

final readonly class CancelErasureRequest
{
    public function __construct(
        public ?string $email = null,
        #[SensitiveParameter]
        public ?string $password = null,
    ) {
    }
}
