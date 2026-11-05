<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

final readonly class HeldRole
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
