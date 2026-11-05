<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\Enum;

enum RequestChannel: string
{
    case App = 'app';
    case Email = 'email';
    case Letter = 'letter';
    case InPerson = 'in_person';
}
