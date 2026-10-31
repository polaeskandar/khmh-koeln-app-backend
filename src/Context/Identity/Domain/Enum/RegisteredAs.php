<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Enum;

enum RegisteredAs: string
{
    case Parent = 'parent';
    case Servant = 'servant';
}
