<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Enum;

enum MembershipEnding: string
{
    case Ended = 'ended';
    case DeleteInstead = 'deleteInstead';
}
