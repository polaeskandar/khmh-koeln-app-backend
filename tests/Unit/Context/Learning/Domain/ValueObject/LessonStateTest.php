<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\ValueObject\LessonState;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class LessonStateTest extends TestCase
{
    public function testArchivingAgainKeepsTheFirstTime(): void
    {
        $first = new DateTimeImmutable('2026-11-01T12:00:00+01:00');

        $state = LessonState::draft()
            ->archivedAt($first)
            ->archivedAt(new DateTimeImmutable('2026-11-02T12:00:00+01:00'));

        self::assertSame($first, $state->archivedAt);
    }
}
