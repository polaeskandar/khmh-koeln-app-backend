<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Enum;

enum GuardianStatus: string
{
    case Approved = 'approved';
    case Revoked = 'revoked';
}
