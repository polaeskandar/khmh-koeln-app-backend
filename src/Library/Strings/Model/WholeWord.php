<?php

declare(strict_types=1);

namespace App\Library\Strings\Model;

final readonly class WholeWord
{
    public static function in(string $text, string $word): bool
    {
        $pattern = '/(?<!\p{L})' . preg_quote(ComposedForm::of($word), delimiter: '/') . '(?!\p{L})/iu';

        return $word !== '' && preg_match($pattern, ComposedForm::of($text)) === 1;
    }
}
