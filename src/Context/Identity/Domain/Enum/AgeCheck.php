<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Enum;

enum AgeCheck: string
{
    case SixteenOrOlder = 'sixteenOrOlder';
    case GuardianConsent = 'guardianConsent';
}
