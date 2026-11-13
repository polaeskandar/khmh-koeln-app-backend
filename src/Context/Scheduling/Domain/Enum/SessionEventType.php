<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Enum;

enum SessionEventType: string
{
    case Created = 'created';
    case Opened = 'opened';
    case Closed = 'closed';
    case Reopened = 'reopened';
    case Canceled = 'canceled';
    case Edited = 'edited';
}
