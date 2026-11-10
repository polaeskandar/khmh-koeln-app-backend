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

    public function testUmlautsAndSharpSMatchInAnyCase(): void
    {
        self::assertTrue(WholeWord::in('Frau MÜLLER kam', 'Müller'));
        self::assertTrue(WholeWord::in('Haus an der Straße', 'straße'));
        self::assertFalse(WholeWord::in('Müllers Haus', 'Müller'));
        self::assertTrue(WholeWord::in('سأل مِينَا', 'مِينَا'));
    }
}
