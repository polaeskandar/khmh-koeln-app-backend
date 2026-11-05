<?php

declare(strict_types=1);

namespace App\Platform\Logging\Model;

use App\Library\Strings\Model\Text;

final readonly class SensitiveText
{
    private const string URL_QUERY = '#((?:https?://|/)[^\s?"\']*)\?[^\s"\']*#';

    private const string KEY_DETAIL = '/DETAIL:\s+Key \(.*?\)=\(.*?\)[^\n]*/s';

    private const string BOUND_PARAMS = '/with params \[.*?\]/s';

    public static function clean(string $text): string
    {
        return Text::of($text)
            ->replaceMatches(self::URL_QUERY, replacement: '$1')
            ->replaceMatches(self::KEY_DETAIL, replacement: 'DETAIL: [redacted]')
            ->replaceMatches(self::BOUND_PARAMS, replacement: 'with params [redacted]')
            ->toString();
    }
}
