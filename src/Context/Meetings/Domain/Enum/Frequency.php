<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Enum;

enum Frequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
