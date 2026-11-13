<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

final readonly class StaffMember
{
    /**
     * @param list<HeldRole> $roles
     */
    public function __construct(
        public string $id,
        public string $displayName,
        public string $username,
        public bool $disabled,
        public array $roles,
    ) {
    }
}
