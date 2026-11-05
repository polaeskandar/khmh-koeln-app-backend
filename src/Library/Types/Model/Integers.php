<?php

declare(strict_types=1);

namespace App\Library\Types\Model;

use UnexpectedValueException;

final readonly class Integers
{
    public static function of(mixed $value): int
    {
        return is_int($value) ? $value : throw new UnexpectedValueException('The value is not an integer.');
    }
}
