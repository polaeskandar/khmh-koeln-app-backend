<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\UseCase\Command\TakeRole;

final readonly class TakeRoleCommand
{
    public function __construct(
        public string $email,
        public string $role,
    ) {
    }
}
