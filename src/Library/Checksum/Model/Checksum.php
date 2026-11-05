<?php

declare(strict_types=1);

namespace App\Library\Checksum\Model;

final readonly class Checksum
{
    public static function fingerprint(string $value): string
    {
        return hash('crc32c', $value);
    }

    public static function crc32(string $value): int
    {
        return crc32($value);
    }

    public static function sha256(string $value): string
    {
        return hash('sha256', $value);
    }
}
