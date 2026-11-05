<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Enum;

enum DuplicatePolicy: string
{
    case Skip = 'skip';
    case Allow = 'allow';
}
