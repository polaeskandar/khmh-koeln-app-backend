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
}
