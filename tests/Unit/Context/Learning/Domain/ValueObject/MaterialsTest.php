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
                ['label' => 'Worksheet', 'url' => 'https://example.org/sheet.pdf'],
                ['label' => 'https://example.org/song', 'url' => 'https://example.org/song'],
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
    }

    public function testALabelHasAtMost120Characters(): void
    {
        $this->assertFieldErrors(['materials.1.label' => ['code' => 'materials.labelTooLong']], [
            ['label' => 'Fine'],
            ['label' => str_repeat('a', times: 121)],
        ]);
    }

    public function testAtMostTenRowsCountAndEmptyRowsDoNot(): void
    {
        $ten = array_fill(start_index: 0, count: 10, value: ['label' => 'Item']);
        self::assertCount(10, Materials::of([...$ten, ['label' => ' ']], 'materials')->toArray());

        $this->assertFieldErrors(['materials' => ['code' => 'materials.tooMany']], [...$ten, ['label' => 'One more']]);
    }

    /**
     * @param array<string, array{code: string}> $expected
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
