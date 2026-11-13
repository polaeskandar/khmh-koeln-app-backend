<?php

declare(strict_types=1);

namespace App\Context\Access\Domain\Enum;

enum RoleScope: string
{
    case Church = 'church';
    case AssignedGroups = 'assignedGroups';
}
