<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\RoleAssignments;

final readonly class TakeRoleWorkflow
{
    public function __construct(
        private RoleAssignments $assignments,
    ) {
    }

    public function __invoke(string $email, string $role): void
    {
        $this->assignments->take($email, $role);
    }
}
