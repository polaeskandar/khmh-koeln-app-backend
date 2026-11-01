<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Strings\Model;

use App\Library\Strings\Model\Text;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    public function testTextKeepsItsValue(): void
    {
        $text = Text::of('alice');

        self::assertSame('alice', $text->toString());
        self::assertSame('alice', (string) $text);
    }

    public function testFormatFillsThePlaceholders(): void
    {
        self::assertSame(
            'Context "people" has 3 tables.',
            Text::format('Context "%s" has %d tables.', 'people', 3)->toString(),
        );
    }

    public function testLowerHandlesNonAsciiCapitals(): void
    {
        self::assertSame('ärger', Text::of('ÄRGER')->lower()->toString());
    }

    public function testStartsWithComparesThePrefix(): void
    {
        self::assertTrue(Text::of('/api/health')->startsWith('/api/'));
        self::assertFalse(Text::of('/apiary')->startsWith('/api/'));
    }

    public function testReplaceMatchesRewritesEveryMatch(): void
    {
        self::assertSame(
            'materials.2.url',
            Text::of('materials[2].url')->replaceMatches('/\[(\d+)]/', '.$1')->toString(),
        );
    }

    public function testMatchesTestsThePattern(): void
    {
        self::assertTrue(Text::of('notFound.service')->matches('/^[a-z][a-zA-Z.]*$/'));
        self::assertFalse(Text::of('Access Denied.')->matches('/^[a-z][a-zA-Z.]*$/'));
    }

    public function testIsDigitsAcceptsOnlyARunOfDigits(): void
    {
        self::assertTrue(Text::of('120')->isDigits());
        self::assertFalse(Text::of('12a')->isDigits());
        self::assertFalse(Text::of('')->isDigits());
    }

    public function testSplitCutsAtEverySeparator(): void
    {
        self::assertSame(['a', ' b', ''], Text::of('a, b,')->split(','));
        self::assertSame([''], Text::of('')->split(','));
    }
}
