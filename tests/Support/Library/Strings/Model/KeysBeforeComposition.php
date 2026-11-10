<?php

declare(strict_types=1);

namespace App\Tests\Support\Library\Strings\Model;

final readonly class KeysBeforeComposition
{
    public static function of(string $name): string
    {
        $spaced = preg_replace('/\s+/u', replacement: ' ', subject: $name) ?? '';
        $collapsed = preg_replace('/^\s+|\s+$/u', replacement: '', subject: $spaced) ?? '';

        return str_replace(search: ' ', replace: '_', subject: mb_strtolower($collapsed));
    }
}
