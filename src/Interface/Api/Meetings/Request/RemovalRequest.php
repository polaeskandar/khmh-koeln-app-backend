<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Request;

final readonly class RemovalRequest
{
    public function __construct(
        public ?string $reason = null,
    ) {
    }
}
