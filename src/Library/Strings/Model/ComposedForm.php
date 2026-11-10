<?php

declare(strict_types=1);

namespace App\Library\Strings\Model;

use Normalizer;

final readonly class ComposedForm
{
    public static function of(string $text): string
    {
        $composed = Normalizer::normalize($text, Normalizer::FORM_C);

        return $composed === false ? $text : $composed;
    }
}
