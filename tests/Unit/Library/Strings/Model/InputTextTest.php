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

    public function testLoweringHandlesUmlauts(): void
    {
        self::assertSame('ältere', InputText::of('ÄLTERE')->lowered()->toString());
    }
}
