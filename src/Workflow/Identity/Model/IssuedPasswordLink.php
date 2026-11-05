<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use DateTimeImmutable;

final readonly class IssuedPasswordLink
{
    public function __construct(
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
