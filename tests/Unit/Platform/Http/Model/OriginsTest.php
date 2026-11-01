<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform\Http\Model;

use App\Platform\Http\Model\Origins;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OriginsTest extends TestCase
{
    public function testAListKeepsEachExactOriginAndSkipsBlanks(): void
    {
        self::assertSame(
            ['http://localhost:3000', 'https://app.khmk-koeln.com'],
            Origins::of(' http://localhost:3000 ,, https://app.khmk-koeln.com '),
        );

        self::assertSame([], Origins::of(''));
    }

    #[DataProvider('inexactOrigins')]
    public function testAnInexactOriginIsRefused(string $entry): void
    {
        $this->expectException(InvalidArgumentException::class);

        Origins::of($entry);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function inexactOrigins(): iterable
    {
        yield 'wildcard' => ['*'];
        yield 'regex' => ['^https?://localhost'];
        yield 'trailing slash' => ['http://localhost:3000/'];
        yield 'path' => ['https://app.khmk-koeln.com/api'];
        yield 'no scheme' => ['localhost:3000'];
        yield 'other scheme' => ['ftp://localhost'];
    }

    public function testTheOriginOfAUrlDropsPathQueryAndFragment(): void
    {
        self::assertSame('https://app.khmk-koeln.com', Origins::ofUrl('https://app.khmk-koeln.com/de/start?x=1#top'));
        self::assertSame('http://localhost:3000', Origins::ofUrl('http://localhost:3000'));
    }
}
