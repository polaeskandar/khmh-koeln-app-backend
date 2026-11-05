<?php

declare(strict_types=1);

namespace App\Library\Strings\Model;

final readonly class WholeWord
{
    public static function in(string $text, string $word): bool
    {
        $pattern = '/(?<!\p{L})' . preg_quote($word, delimiter: '/') . '(?!\p{L})/iu';

        return $word !== '' && preg_match($pattern, $text) === 1;
    }
}
