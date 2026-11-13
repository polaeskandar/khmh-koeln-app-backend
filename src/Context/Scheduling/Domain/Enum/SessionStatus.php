<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Enum;

enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case Canceled = 'canceled';
}
