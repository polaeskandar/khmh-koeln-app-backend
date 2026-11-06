<?php

declare(strict_types=1);

namespace App\Platform\Logging\Model;

use App\Library\Strings\Model\Text;

final readonly class SensitiveText
{
    private const string URL_EXTRAS = '~((?:https?://|/)[^\s?#"\']*)[?#][^\s"\']*~';

    private const string DETAIL = '/DETAIL:[^\n]*/';

    private const string EMAIL = '/[^\s"\'<>@()\[\]]+@[^\s"\'<>@()\[\]]+\.[A-Za-z]{2,}/';

    private const string COMMAND_ARGUMENTS = '/^(\S+).*$/s';

    public static function clean(string $text): string
    {
        return Text::of($text)
            ->replaceMatches(self::URL_EXTRAS, replacement: '$1')
            ->replaceMatches(self::DETAIL, replacement: 'DETAIL: [redacted]')
            ->replaceMatches(self::EMAIL, replacement: '[email]')
            ->toString();
    }

    public static function commandName(string $commandLine): string
    {
        return Text::of($commandLine)->replaceMatches(
            self::COMMAND_ARGUMENTS,
            replacement: '$1 [redacted]',
        )->toString();
    }
}
