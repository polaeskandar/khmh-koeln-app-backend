<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Enum\Frequency;
use App\Context\Meetings\Domain\ValueObject\DateSpan;
use App\Context\Meetings\Domain\ValueObject\Recurrence;
use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class RecurrenceDraftTest extends TestCase
{
    public function testAPassingDraftBuildsItsRecurrence(): void
    {
        self::assertSame(2, self::draft(interval: 2)->toRecurrence()->interval);
    }

    public function testABrokenDraftBuildsNoRecurrence(): void
    {
        self::assertSame(
            ['recurrence.interval' => ['code' => 'recurrence.interval']],
            ThrownFieldErrors::of(static fn (): Recurrence => self::draft(interval: 53)->toRecurrence()),
        );
    }

    private static function draft(int $interval): RecurrenceDraft
    {
        return new RecurrenceDraft(
            Frequency::Weekly,
            $interval,
            weekdays: [7],
            monthWeek: null,
            span: new DateSpan(LocalDate::of('2026-11-01'), null),
        );
    }
}
