<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Enum;

enum RosterMode: string
{
    case Expected = 'expected';
    case Open = 'open';
}
