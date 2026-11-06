<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\ValueObject\DateSpan;
use App\Context\Meetings\Domain\ValueObject\Recurrence;
use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Context\Meetings\Domain\ValueObject\RecurrenceCases;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecurrenceOccurrencesTest extends TestCase
{
    /**
     * @param list<string> $dates
     */
    #[DataProvider('goldenVectors')]
    public function testTheGoldenVectorsHold(RecurrenceDraft $draft, string $from, string $to, array $dates): void
    {
        $occurrences = $draft->toRecurrence()->occurrencesBetween(LocalDate::of($from), LocalDate::of($to));

        self::assertSame($dates, array_map(static fn (LocalDate $date): string => $date->toString(), $occurrences));
    }

    /**
     * @return iterable<string, array{RecurrenceDraft, string, string, list<string>}>
     */
    public static function goldenVectors(): iterable
    {
        return RecurrenceCases::occurrences();
    }

    #[DataProvider('patterns')]
    public function testOccurrencesAreTheDaysThatOccur(Recurrence $recurrence): void
    {
        $from = LocalDate::of('2026-01-01');
        $days = array_map($from->addDays(...), range(start: 0, end: 730));
        $occurring = array_values(array_filter($days, $recurrence->occursOn(...)));

        self::assertEquals($occurring, $recurrence->occurrencesBetween($from, $from->addDays(730)));
    }

    /**
     * @return iterable<string, array{Recurrence}>
     */
    public static function patterns(): iterable
    {
        $span = new DateSpan(LocalDate::of('2026-03-11'), LocalDate::of('2027-09-30'));

        yield 'weekly' => [Recurrence::weekly(1, [3, 7], $span)];
        yield 'every 3 weeks' => [Recurrence::weekly(3, [1], $span)];
        yield 'second Tuesday' => [Recurrence::monthly(2, 2, $span)];
        yield 'last Friday every 2 months' => [Recurrence::monthly(-1, 5, $span, interval: 2)];
    }

    public function testTheNextOccurrenceStopsAtTheEnd(): void
    {
        $recurrence = Recurrence::weekly(
            1,
            [7],
            new DateSpan(LocalDate::of('2026-11-01'), LocalDate::of('2026-11-08')),
        );

        self::assertSame('2026-11-08', $recurrence->nextOccurrence(LocalDate::of('2026-11-02'))?->toString());
        self::assertNull($recurrence->nextOccurrence(LocalDate::of('2026-11-09')));
    }

    public function testTheNextOccurrenceOfALongMonthlyCycleIsFound(): void
    {
        $recurrence = Recurrence::monthly(1, 4, new DateSpan(LocalDate::of('2026-01-01'), null), interval: 14);

        self::assertSame('2027-03-04', $recurrence->nextOccurrence(LocalDate::of('2026-01-02'))?->toString());
    }

    public function testTheNextOccurrenceIsFoundFromAFarStart(): void
    {
        $recurrence = Recurrence::weekly(1, [5], new DateSpan(LocalDate::of('2028-01-07'), null));

        self::assertSame('2028-01-07', $recurrence->nextOccurrence(LocalDate::of('2026-01-01'))?->toString());
    }
}
