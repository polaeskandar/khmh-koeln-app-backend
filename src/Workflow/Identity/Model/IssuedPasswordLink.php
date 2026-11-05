<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class IssuedPasswordLink
{
    public function __construct(
        public string $expiresAt,
    ) {
    }
}
