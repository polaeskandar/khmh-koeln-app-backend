<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Validation;

use App\Context\Scheduling\Domain\Enum\Frequency;
use App\Context\Scheduling\Domain\Validation\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\DateSpan;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceDraft;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Context\Scheduling\Domain\ValueObject\RecurrenceCases;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecurrenceRuleTest extends TestCase
{
    #[DataProvider('errors')]
    public function testEachBrokenRuleNamesItsCodeAndField(RecurrenceDraft $draft, string $code, string $field): void
    {
        self::assertSame(['recurrence.' . $field => ['code' => $code]], self::fieldErrorsOf($draft));
    }

    /**
     * @return iterable<string, array{RecurrenceDraft, string, string}>
     */
    public static function errors(): iterable
    {
        return RecurrenceCases::errors();
    }

    public function testTheGoldenVectorsFollowTheRules(): void
    {
        foreach (RecurrenceCases::occurrences() as $name => [$draft]) {
            self::assertSame([], self::fieldErrorsOf($draft), $name);
        }
    }

    public function testEveryBrokenFieldIsReportedOnce(): void
    {
        $draft = new RecurrenceDraft(
            Frequency::Weekly,
            interval: 0,
            weekdays: [1],
            monthWeek: null,
            span: new DateSpan(LocalDate::of('2026-09-01'), LocalDate::of('2026-08-31')),
        );

        self::assertSame(['recurrence.interval', 'recurrence.span.endsOn'], array_keys(self::fieldErrorsOf($draft)));
    }

    public function testAFieldReportsOnlyItsFirstBrokenRule(): void
    {
        self::assertSame(
            ['recurrence.weekdays' => ['code' => 'recurrence.weekdays']],
            self::fieldErrorsOf(self::monthly([], monthWeek: 1)),
        );

        self::assertSame(
            [
                'recurrence.weekdays' => ['code' => 'recurrence.weekdayRange'],
                'recurrence.monthWeek' => ['code' => 'recurrence.monthWeek'],
            ],
            self::fieldErrorsOf(self::monthly([0, 8], monthWeek: 5)),
        );
    }

    public function testAPassingDraftIsAccepted(): void
    {
        $draft = new RecurrenceDraft(
            Frequency::Weekly,
            interval: 2,
            weekdays: [7],
            monthWeek: null,
            span: new DateSpan(LocalDate::of('2026-11-01'), null),
        );

        self::assertSame([], self::fieldErrorsOf($draft));
    }

    /**
     * @param list<int> $weekdays
     */
    private static function monthly(array $weekdays, int $monthWeek): RecurrenceDraft
    {
        return new RecurrenceDraft(
            Frequency::Monthly,
            interval: 1,
            weekdays: $weekdays,
            monthWeek: $monthWeek,
            span: new DateSpan(LocalDate::of('2026-11-01'), null),
        );
    }

    /**
     * @return array<string, array{code: string, params?: array<string, string|int|float|bool|null>}>
     */
    private static function fieldErrorsOf(RecurrenceDraft $draft): array
    {
        try {
            RecurrenceRule::ensureValid($draft);
        } catch (InvalidInput $invalid) {
            return $invalid->fieldErrors;
        }

        return [];
    }
}
