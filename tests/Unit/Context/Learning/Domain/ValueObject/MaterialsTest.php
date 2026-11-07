<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\ValueObject\Materials;
use App\Library\Problem\Exception\InvalidInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MaterialsTest extends TestCase
{
    public function testRowsAreCleanedEmptyOnesDroppedAndAUrlLabelsItself(): void
    {
        $materials = Materials::of([
            ['label' => '  Worksheet ', 'url' => ' https://example.org/sheet.pdf '],
            ['label' => '   ', 'url' => ''],
            ['url' => 'https://example.org/song'],
        ], 'materials');

        self::assertSame(
            [
                ['url' => 'https://example.org/sheet.pdf', 'label' => 'Worksheet'],
                ['url' => 'https://example.org/song', 'label' => 'https://example.org/song'],
            ],
            $materials->toArray(),
        );
    }

    #[DataProvider('badUrls')]
    public function testOnlyWebAddressesAreLinks(string $url): void
    {
        $this->assertFieldErrors(['materials.0.url' => ['code' => 'materials.url']], [[
            'label' => 'Link',
            'url' => $url,
        ]]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badUrls(): iterable
    {
        yield 'script' => ['javascript:alert(1)'];
        yield 'ftp' => ['ftp://x'];
        yield 'space' => ['https://a b'];
        yield 'credentials' => ['https://trusted.org@evil.example/'];
        yield 'bidi control' => ["https://example.org/\u{202E}fdp.exe"];
        yield 'angle bracket' => ['https://example.org/<script>'];
    }

    public function testWebAddressesInAnyCaseAndWithoutTlsAreLinks(): void
    {
        $rows = [
            ['url' => 'http://example.org'],
            ['url' => 'HTTPS://Example.org/a?b=c@d#e'],
            ['url' => 'https://medium.com/@user'],
        ];

        self::assertCount(3, Materials::of($rows, 'materials')->toArray());
    }

    public function testALongLinkNeedsNoLabelAndALinkHasALimit(): void
    {
        $long = 'https://example.org/' . str_repeat('a', times: 200);

        self::assertSame(
            [['url' => $long, 'label' => $long]],
            Materials::of([['url' => $long]], 'materials')->toArray(),
        );

        $this->assertFieldErrors([
            'materials.0.url' => ['code' => 'validation.tooLong', 'params' => ['max' => 2048]],
        ], [['url' => 'https://example.org/' . str_repeat('a', times: 2029)]]);
    }

    public function testALabelHasAtMost120Characters(): void
    {
        self::assertCount(1, Materials::of([['label' => str_repeat('a', times: 120)]], 'materials')->toArray());

        $this->assertFieldErrors(['materials.2.label' => ['code' => 'materials.labelTooLong']], [
            ['label' => 'Fine'],
            ['label' => ' '],
            ['label' => str_repeat('a', times: 121)],
        ]);
    }

    public function testAtMostTenRowsCountAndEmptyRowsDoNot(): void
    {
        $ten = array_fill(start_index: 0, count: 10, value: ['label' => 'Item']);
        self::assertCount(10, Materials::of([...$ten, ['label' => ' ']], 'materials')->toArray());

        $this->assertFieldErrors(['materials' => ['code' => 'materials.tooMany']], [...$ten, ['label' => 'One more']]);

        $this->assertFieldErrors([
            'materials' => ['code' => 'materials.tooMany'],
            'materials.10.url' => ['code' => 'materials.url'],
        ], [...$ten, ['label' => 'One more', 'url' => 'ftp://x']]);
    }

    /**
     * @param array<string, array{code: string, params?: array<string, int>}> $expected
     * @param list<array<string, string>> $rows
     */
    private function assertFieldErrors(array $expected, array $rows): void
    {
        try {
            Materials::of($rows, 'materials');
        } catch (InvalidInput $invalid) {
            self::assertSame($expected, $invalid->fieldErrors);

            return;
        }

        self::fail('Expected field errors.');
    }
}
