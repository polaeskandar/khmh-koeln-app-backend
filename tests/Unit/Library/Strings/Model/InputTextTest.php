<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Strings\Model;

use App\Library\Strings\Model\InputText;
use PHPUnit\Framework\TestCase;

final class InputTextTest extends TestCase
{
    public function testTrimmingRemovesUnicodeSpaceAtBothEndsOnly(): void
    {
        self::assertSame('Choir  practice', InputText::of("\u{00A0} Choir  practice\n")->trimmed()->toString());
    }

    public function testCollapsingLeavesSingleSpacesBetweenWords(): void
    {
        self::assertSame(
            'Youth Meeting',
            InputText::of("Youth\u{00A0}\u{2003}\t Meeting\u{3000}")->collapsed()->toString(),
        );

        self::assertSame('3rd Class', InputText::of('  3rd  Class ')->collapsed()->toString());
    }

    public function testLengthCountsCharactersNotBytes(): void
    {
        self::assertSame(6, InputText::of('Ältere')->length());
        self::assertSame(4, InputText::of('أحد ')->length());
    }

    public function testOnlyNoTextIsEmpty(): void
    {
        self::assertTrue(InputText::of(null)->isEmpty());
        self::assertNull(InputText::of(' ')->trimmed()->toStringOrNull());
        self::assertSame('x', InputText::of('x')->toStringOrNull());
        self::assertTrue(InputText::of('')->isEmpty());
        self::assertTrue(InputText::of(' ')->collapsed()->isEmpty());
        self::assertFalse(InputText::of(' ')->isEmpty());
    }

    public function testFoldingDropsCaseAccentsAndArabicVowelMarks(): void
    {
        self::assertSame('monika hanna', InputText::of(' Mönika   Ḥanna ')->folded()->toString());
        self::assertSame('مينا', InputText::of('مِينَا')->folded()->toString());
    }

    public function testZeroWidthCharactersAreNotSpace(): void
    {
        self::assertSame("\u{FEFF}Choir", InputText::of("\u{3000}\u{FEFF}Choir\u{00A0}")->collapsed()->toString());
        self::assertSame("می\u{200C}خواهم", InputText::of(" می\u{200C}خواهم ")->collapsed()->toString());
        self::assertSame(6, InputText::of("\u{FEFF}Choir")->length());
        self::assertSame(8, InputText::of("می\u{200C}خواهم")->length());
    }

    public function testFoldingKeepsSharpSAndTheZeroWidthNonJoinerButDropsArabicMarks(): void
    {
        self::assertSame('straße groß', InputText::of('STRAẞE  Groß')->folded()->toString());
        self::assertSame("می\u{200C}خواهم", InputText::of("می\u{200C}خواهم")->folded()->toString());
        self::assertSame('مدارس الاحد', InputText::of('مدارس الأحد')->folded()->toString());
        self::assertSame('محبة', InputText::of('محبّة')->folded()->toString());
    }

    public function testLoweringKeepsSharpSAndUmlauts(): void
    {
        self::assertSame('straße über', InputText::of('STRAẞE ÜBER')->lowered()->toString());
        self::assertSame('مدارس الأحد', InputText::of('مدارس الأحد')->lowered()->toString());
    }
}
