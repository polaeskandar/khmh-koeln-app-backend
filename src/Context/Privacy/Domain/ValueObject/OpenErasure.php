<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\ValueObject;

final readonly class OpenErasure
{
    public function __construct(
        public string $receivedAt,
        public string $dueOn,
    ) {
    }
}
