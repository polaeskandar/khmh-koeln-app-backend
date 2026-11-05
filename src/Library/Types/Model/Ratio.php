<?php

declare(strict_types=1);

namespace App\Library\Types\Model;

final readonly class Ratio
{
    private const int PRECISION = 2;

    public static function of(int $part, int $whole): ?float
    {
        return $whole === 0 ? null : round($part / $whole, self::PRECISION);
    }

    public static function mean(int $sum, int $count): ?int
    {
        return $count === 0 ? null : (int) round($sum / $count);
    }
}
