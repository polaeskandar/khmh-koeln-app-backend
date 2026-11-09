<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\RoleAssignmentsByEmail;

final readonly class TakeRoleByEmailWorkflow
{
    public function __construct(
        private RoleAssignmentsByEmail $assignments,
    ) {
    }

    public function __invoke(string $email, string $role): void
    {
        $this->assignments->take($email, $role);
    }
}
