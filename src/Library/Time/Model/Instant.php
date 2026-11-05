<?php

declare(strict_types=1);

namespace App\Library\Time\Model;

use DateTimeImmutable;
use DateTimeInterface;

final readonly class Instant
{
    public static function parse(string $value): ?DateTimeImmutable
    {
        $instant = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);

        return $instant !== false && $instant->format(DateTimeInterface::ATOM) === $value ? $instant : null;
    }
}
