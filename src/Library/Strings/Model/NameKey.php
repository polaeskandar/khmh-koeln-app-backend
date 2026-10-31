<?php

declare(strict_types=1);

namespace App\Library\Strings\Model;

final readonly class NameKey
{
    public static function of(string $name): string
    {
        return InputText::of($name)->collapsed()->lowered()->toString() |> self::joined(...);
    }

    private static function joined(string $words): string
    {
        return str_replace(search: ' ', replace: '_', subject: $words);
    }
}
