<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Strings\Model;

use App\Library\Strings\Model\WholeWord;
use PHPUnit\Framework\TestCase;

final class WholeWordTest extends TestCase
{
    public function testAWordMatchesOnlyBetweenNonLetters(): void
    {
        self::assertTrue(WholeWord::in('Kiro was there but not ticked.', 'Kiro'));
        self::assertTrue(WholeWord::in('ask KIRO, then Mina', 'kiro'));
        self::assertTrue(WholeWord::in('غاب مينا اليوم', 'مينا'));
        self::assertFalse(WholeWord::in('Kirollosx came late', 'Kiro'));
        self::assertFalse(WholeWord::in('Mikiro came', 'Kiro'));
        self::assertFalse(WholeWord::in('aab', 'a+b'));
    }

    public function testAWordMatchesInEitherForm(): void
    {
        self::assertTrue(WholeWord::in("Frau Mu\u{0308}ller kam nicht", "M\u{00FC}ller"));
        self::assertTrue(WholeWord::in("Frau M\u{00FC}ller kam nicht", "Mu\u{0308}ller"));
        self::assertTrue(WholeWord::in("غاب ال\u{0627}\u{0654}خ", 'الأخ'));
        self::assertFalse(WholeWord::in("Mu\u{0308}ller", 'Mu'));
    }

    public function testUmlautsAndSharpSMatchInAnyCase(): void
    {
        self::assertTrue(WholeWord::in('Frau MÜLLER kam', 'Müller'));
        self::assertTrue(WholeWord::in('Haus an der Straße', 'straße'));
        self::assertFalse(WholeWord::in('Müllers Haus', 'Müller'));
        self::assertTrue(WholeWord::in('سأل مِينَا', 'مِينَا'));
    }
}
