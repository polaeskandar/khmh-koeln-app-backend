<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Enum;

enum GuardianKind: string
{
    case Parent = 'parent';
    case Guardian = 'guardian';
}
