<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

final readonly class StaffMember
{
    public function __construct(
        public string $id,
        public string $displayName,
        public string $username,
        public bool $disabled,
    ) {
    }
}
