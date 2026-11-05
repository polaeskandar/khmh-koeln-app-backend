<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;
use InvalidArgumentException;

final readonly class ImportColumns
{
    private const array REQUIRED = ['first_name', 'last_name', 'service'];

    private const array KNOWN = ['first_name', 'last_name', 'display_name', 'service', 'stage'];

    private const string UNKNOWN = 'The column "%s" is not one of first_name, last_name, display_name, service, stage.';

    private const string MISSING = 'The column "%s" is missing.';

    /**
     * @param list<string> $columns
     */
    public static function ensureKnown(array $columns): void
    {
        $given = new ArrayCollection($columns);
        $known = new ArrayCollection(self::KNOWN);
        $unknown = $given->filter(static fn (string $column): bool => !$known->contains($column))->first();

        if ($unknown !== false) {
            throw new InvalidArgumentException(Text::format(self::UNKNOWN, $unknown)->toString());
        }

        $missing = new ArrayCollection(self::REQUIRED)
            ->filter(static fn (string $column): bool => !$given->contains($column))
            ->first();

        if ($missing !== false) {
            throw new InvalidArgumentException(Text::format(self::MISSING, $missing)->toString());
        }
    }
}
