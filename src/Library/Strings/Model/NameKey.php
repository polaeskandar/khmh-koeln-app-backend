<?php

declare(strict_types=1);

namespace App\Library\Strings\Model;

final readonly class NameKey
{
    public static function of(string $name): string
    {
        return InputText::of($name)->collapsed()->toString() |> mb_strtolower(...);
    }
}
