<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Enum;

enum PersonStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
