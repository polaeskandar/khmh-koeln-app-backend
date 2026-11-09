<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api\Privacy;

use PHPUnit\Framework\Assert;

final readonly class CopyLinks
{
    public static function tokenOf(string $url): string
    {
        $matches = [];
        Assert::assertSame(1, preg_match('/#token=([A-Za-z0-9_-]{43})$/', $url, $matches), $url);

        return $matches[1] ?? '';
    }
}
