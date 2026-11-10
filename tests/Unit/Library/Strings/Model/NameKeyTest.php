<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Strings\Model;

use App\Library\Strings\Model\NameKey;
use PHPUnit\Framework\TestCase;

final class NameKeyTest extends TestCase
{
    public function testCaseAndExtraSpacesDoNotMakeANameDifferent(): void
    {
        self::assertSame(NameKey::of('3rd Class'), NameKey::of('  3rd  class'));
        self::assertSame('3rd_class', NameKey::of("\t3rd\n Class "));
    }

    public function testNoBreakSpacesCollapseLikeOrdinaryOnes(): void
    {
        self::assertSame(NameKey::of('Youth Meeting'), NameKey::of("Youth\u{00A0}\u{2003}Meeting\u{3000}"));
    }

    public function testUmlautsAreLowered(): void
    {
        self::assertSame('ältere_jugend', NameKey::of(' ÄLTERE   Jugend '));
    }

    public function testArabicIsLeftAsItIs(): void
    {
        self::assertSame('مدارس_الأحد', NameKey::of('مدارس  الأحد'));
    }

    public function testSharpSIsKeptAndTheCapitalOneLowered(): void
    {
        self::assertSame('straße_groß', NameKey::of('STRAẞE Groß'));
    }

    public function testArabicVowelMarksAndTheZeroWidthNonJoinerStayInTheKey(): void
    {
        self::assertSame('مِينَا_حَنَّا', NameKey::of(" مِينَا\u{3000}حَنَّا "));
        self::assertSame("می\u{200C}خواهم", NameKey::of("می\u{200C}خواهم"));
    }

    public function testAByteOrderMarkIsNotSpace(): void
    {
        self::assertSame("\u{FEFF}choir", NameKey::of(" \u{FEFF}Choir"));
    }
}
