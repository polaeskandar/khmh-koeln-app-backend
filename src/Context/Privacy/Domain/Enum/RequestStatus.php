<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\Enum;

enum RequestStatus: string
{
    case Open = 'open';
    case Done = 'done';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
}
