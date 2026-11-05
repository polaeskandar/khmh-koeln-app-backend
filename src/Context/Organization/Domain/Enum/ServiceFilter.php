<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Enum;

enum ServiceFilter: string
{
    case Active = 'active';
    case Archived = 'archived';
    case All = 'all';
}
