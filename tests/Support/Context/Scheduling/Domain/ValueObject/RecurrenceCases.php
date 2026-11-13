<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Enum\Frequency;
use App\Context\Scheduling\Domain\ValueObject\DateSpan;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceDraft;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Type;
use App\Tests\Support\JsonBody;

final readonly class RecurrenceCases
{
    private const string FILE = __DIR__ . '/../../../../../Fixtures/recurrence-cases.json';

    /**
     * @return iterable<string, array{RecurrenceDraft, string, string, list<string>}>
     */
    public static function occurrences(): iterable
    {
        foreach (self::section('occurrences') as $case) {
            yield self::text($case, 'name') => [
                self::draft(JsonBody::asArray($case['recurrence'] ?? null, 'recurrence')),
                self::text($case, 'from'),
                self::text($case, 'to'),
                array_values(array_map(strval(...), JsonBody::asArray($case['dates'] ?? null, 'dates'))),
            ];
        }
    }

    /**
     * @return iterable<string, array{RecurrenceDraft, string, string}>
     */
    public static function errors(): iterable
    {
        foreach (self::section('errors') as $case) {
            $error = JsonBody::asArray($case['error'] ?? null, 'error');

            yield self::text($case, 'name') => [
                self::draft(JsonBody::asArray($case['recurrence'] ?? null, 'recurrence')),
                self::text($error, 'code'),
                self::text($error, 'field'),
            ];
        }
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private static function section(string $name): array
    {
        $cases = JsonBody::asArray(
            json_decode((string) file_get_contents(self::FILE), associative: true, flags: JSON_THROW_ON_ERROR),
            'cases',
        );

        return array_values(array_map(
            static fn (mixed $case): array => JsonBody::asArray($case, 'case'),
            JsonBody::asArray($cases[$name] ?? null, $name),
        ));
    }

    /**
     * @param array<array-key, mixed> $recurrence
     */
    private static function draft(array $recurrence): RecurrenceDraft
    {
        $monthWeek = Type::of($recurrence['monthWeek'] ?? null)->toScalarOrNull();

        return new RecurrenceDraft(
            frequency: Frequency::from(self::text($recurrence, 'frequency')),
            interval: (int) Type::of($recurrence['interval'] ?? null)->toScalarOrNull(),
            weekdays: array_values(array_map(
                intval(...),
                JsonBody::asArray($recurrence['weekdays'] ?? null, 'weekdays'),
            )),
            monthWeek: $monthWeek === null ? null : (int) $monthWeek,
            span: self::span(JsonBody::asArray($recurrence['span'] ?? null, 'span')),
        );
    }

    /**
     * @param array<array-key, mixed> $span
     */
    private static function span(array $span): DateSpan
    {
        $endsOn = Type::of($span['endsOn'] ?? null)->toStringOrNull();

        return new DateSpan(
            LocalDate::of(self::text($span, 'startsOn')),
            $endsOn === null ? null : LocalDate::of($endsOn),
        );
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function text(array $values, string $key): string
    {
        return Type::of($values[$key] ?? null)->toString();
    }
}
