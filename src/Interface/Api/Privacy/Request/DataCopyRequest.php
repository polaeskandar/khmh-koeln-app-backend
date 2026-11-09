<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Request;

use SensitiveParameter;

final readonly class DataCopyRequest
{
    public function __construct(
        #[SensitiveParameter]
        public ?string $token = null,
    ) {
    }
}
