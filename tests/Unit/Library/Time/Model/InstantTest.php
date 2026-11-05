<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Time\Model;

use App\Library\Time\Model\Instant;
use PHPUnit\Framework\TestCase;

final class InstantTest extends TestCase
{
    public function testOnlyAnExactInstantWithOffsetParses(): void
    {
        self::assertSame(
            '2026-11-08T17:00:00+01:00',
            Instant::parse('2026-11-08T17:00:00+01:00')?->format('Y-m-d\TH:i:sP'),
        );

        self::assertNull(Instant::parse('2026-11-08 17:00'));
        self::assertNull(Instant::parse('2026-02-30T17:00:00+01:00'));
        self::assertNull(Instant::parse('tomorrow'));
    }
}
