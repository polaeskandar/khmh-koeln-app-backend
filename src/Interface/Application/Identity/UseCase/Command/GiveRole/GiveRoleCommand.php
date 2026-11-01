<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\UseCase\Command\GiveRole;

final readonly class GiveRoleCommand
{
    public function __construct(
        public string $email,
        public string $role,
    ) {
    }
}
