<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Enum;

enum Action: string
{
    case Create = 'create';
    case Read = 'read';
    case Update = 'update';
    case Delete = 'delete';
}
