<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Enum;

enum RoleScope: string
{
    case Church = 'church';
    case AssignedGroups = 'assignedGroups';
}
