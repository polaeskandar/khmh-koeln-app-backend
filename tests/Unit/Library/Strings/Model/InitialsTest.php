<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Strings\Model;

use App\Library\Strings\Model\Initials;
use PHPUnit\Framework\TestCase;

final class InitialsTest extends TestCase
{
    public function testEachWordGivesItsFirstCharacterAfterTheSpaceAroundIt(): void
    {
        self::assertSame('MH', Initials::of('Mina', 'Hanna'));
        self::assertSame('MH', Initials::of(" Mina\t", "\nHanna "));
        self::assertSame('ÄÜ', Initials::of('Älter', 'Über'));
        self::assertSame('ḤM', Initials::of('Ḥanna', 'Müller'));
        self::assertSame('M', Initials::of('Mina', ''));
    }

    public function testArabicInitialsAreTheLettersWithoutTheirVowelMarks(): void
    {
        self::assertSame('مح', Initials::of('مِينَا', 'حَنَّا'));
        self::assertSame('أع', Initials::of('أحمد', 'عبد'));
    }

    public function testAnInitialIsOneCodePointNotOneVisibleCharacter(): void
    {
        self::assertSame('A', Initials::of("A\u{0308}lter"));
        self::assertSame("\u{1F468}", Initials::of("\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467} Family"));
        self::assertSame("\u{00A0}", Initials::of("\u{00A0}Mina"));
    }
}
