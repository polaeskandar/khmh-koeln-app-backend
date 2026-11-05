<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\Enum;

enum RequestKind: string
{
    case Erasure = 'erasure';
    case Copy = 'copy';
}
