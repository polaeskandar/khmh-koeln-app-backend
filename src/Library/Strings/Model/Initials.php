<?php

declare(strict_types=1);

namespace App\Library\Strings\Model;

final readonly class Initials
{
    public static function of(string ...$words): string
    {
        return implode('', array_map(static fn (string $word): string => mb_substr(
            trim($word),
            start: 0,
            length: 1,
        ), $words));
    }
}
