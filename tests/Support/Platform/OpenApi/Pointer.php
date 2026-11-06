<?php

declare(strict_types=1);

namespace App\Tests\Support\Platform\OpenApi;

final readonly class Pointer
{
    /**
     * @param list<string> $segments
     */
    public static function of(array $segments): string
    {
        return implode('/', array_map(self::escaped(...), $segments));
    }

    public static function escaped(string $segment): string
    {
        return rawurlencode(strtr($segment, ['~' => '~0', '/' => '~1']));
    }
}
