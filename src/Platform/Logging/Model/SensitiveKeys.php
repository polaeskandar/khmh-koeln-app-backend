<?php

declare(strict_types=1);

namespace App\Platform\Logging\Model;

use App\Library\Strings\Model\Text;

final readonly class SensitiveKeys
{
    public const string REDACTED = '[redacted]';

    private const string WORDS = 'email|password|token|cookie|name|note|params|command|secret|authorization|reason';

    public static function matches(string|int $key): bool
    {
        return Text::of((string) $key)->matches('/^(q|.*(' . self::WORDS . ').*)$/i');
    }
}
