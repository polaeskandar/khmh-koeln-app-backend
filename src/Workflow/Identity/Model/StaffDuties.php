<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Workflow\Access\Model\RoleSummary;
use App\Workflow\Organization\Model\AssignmentView;

final readonly class StaffDuties
{
    /**
     * @param list<RoleSummary> $roles
     * @param list<AssignmentView> $assignments
     */
    public function __construct(
        public array $roles,
        public array $assignments,
    ) {
    }
}
