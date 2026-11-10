<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Strings\Model;

use App\Library\Strings\Model\Text;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
        self::assertTrue(Text::of('/api')->startsWith(''));
        self::assertTrue(Text::of('')->startsWith(''));
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

    public function testIsUtf8RejectsBrokenByteSequences(): void
    {
        self::assertTrue(Text::of('Mönika مينا')->isUtf8());
        self::assertFalse(Text::of("\xC3\x28")->isUtf8());
    }

    public function testIsUtf8AcceptsEveryScalarValueAndNothingElse(): void
    {
        self::assertTrue(Text::of('')->isUtf8());
        self::assertTrue(Text::of("\u{1F600}\u{FFFF}\u{10FFFF}\u{FEFF}\u{200C}")->isUtf8());
        self::assertFalse(Text::of("\xED\xA0\x80")->isUtf8());
        self::assertFalse(Text::of("\xC0\x80")->isUtf8());
        self::assertFalse(Text::of("\xF4\x90\x80\x80")->isUtf8());
        self::assertFalse(Text::of("\xE2\x82")->isUtf8());
        self::assertFalse(Text::of("Chor \xFF")->isUtf8());
    }

    public function testLowerFollowsUnicodeCaseMappingWithoutComposing(): void
    {
        self::assertSame('straße', Text::of('STRAẞE')->lower()->toString());
        self::assertSame("i\u{0307}", Text::of('İ')->lower()->toString());
        self::assertSame("u\u{0308}", Text::of("U\u{0308}")->lower()->toString());
        self::assertSame('مدارس الأحد', Text::of('مدارس الأحد')->lower()->toString());
    }

    public function testMatchesAnswersNoWhenAUnicodePatternMeetsBrokenBytes(): void
    {
        self::assertTrue(Text::of('mina@example.org')->matches('/^[^\s@]+@[^\s@]+$/u'));
        self::assertFalse(Text::of("mina\xFF@example.org")->matches('/^[^\s@]+@[^\s@]+$/u'));
    }

    public function testReplaceMatchesWorksOnBytesUnlessThePatternIsUnicode(): void
    {
        self::assertSame("Chor \xFF", Text::of("Chor  \xFF")->replaceMatches('/ +/', ' ')->toString());
        self::assertSame('مدارس_الأحد', Text::of("مدارس\u{00A0}الأحد")->replaceMatches('/\s/u', '_')->toString());
        self::assertSame("مدارس\u{00A0}الأحد", Text::of("مدارس\u{00A0}الأحد")->replaceMatches('/\s/', '_')->toString());
    }

    public function testAPatternThatDoesNotCompileFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/missing closing parenthesis/');

        Text::of('Choir')->replaceMatches('/(/', '');
    }

    public function testAUnicodePatternFailsOnBrokenBytesNamingThePcreError(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/PREG_BAD_UTF8_ERROR/');

        Text::of("Chor \xFF")->replaceMatches('/\s+/u', ' ');
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
