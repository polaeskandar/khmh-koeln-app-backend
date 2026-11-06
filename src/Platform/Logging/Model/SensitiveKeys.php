<?php

declare(strict_types=1);

namespace App\Platform\Logging\Model;

use App\Library\Strings\Model\Text;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class SensitiveKeys
{
    public const string REDACTED = '[redacted]';

    private const string WORDS = 'email|password|token|cookie|name|note|params|command|secret|authorization|reason';

    private const string COMMAND = 'command';

    private const array DIAGNOSTIC = ['token_class', 'firewall_name', 'transport_name', 'transport_names', 'filename'];

    public static function matches(string|int $key): bool
    {
        $name = Text::of((string) $key)->lower()->toString();
        $diagnostic = new ArrayCollection(self::DIAGNOSTIC)->contains($name);

        return !$diagnostic && Text::of($name)->matches('/^(q|.*(' . self::WORDS . ').*)$/');
    }

    public static function redacted(string|int $key, mixed $value): string
    {
        $commandLine = $key === self::COMMAND ? Type::of($value)->toStringOrNull() : null;

        return $commandLine === null ? self::REDACTED : SensitiveText::commandName($commandLine);
    }
}
