<?php

declare(strict_types=1);

namespace App\Platform\Logging\Model;

use App\Library\Collections\Model\Collections;
use App\Library\Types\Model\Type;
use Throwable;

final readonly class SensitiveValues
{
    public static function entry(mixed $value, string|int $key): mixed
    {
        if (SensitiveKeys::matches($key)) {
            return SensitiveKeys::redacted($key, $value);
        }

        if ($value instanceof Throwable) {
            return RedactedFailure::of($value);
        }

        return self::text(Collections::mapItems($value, self::entry(...)));
    }

    private static function text(mixed $value): mixed
    {
        $text = Type::of($value)->toStringOrNull();

        return $text === null ? $value : SensitiveText::clean($text);
    }
}
