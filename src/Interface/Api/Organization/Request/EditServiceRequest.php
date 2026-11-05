<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization\Request;

final readonly class EditServiceRequest
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
    ) {
    }
}
