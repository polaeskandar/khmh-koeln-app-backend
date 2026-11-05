<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Meetings\Application\Service;

use App\Context\Meetings\Application\Service\RecurrenceRules;
use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;
use App\Library\Problem\Exception\InvalidInput;
use App\Tests\Support\Context\Meetings\Domain\ValueObject\RecurrenceCases;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecurrenceRulesTest extends TestCase
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

    /**
     * @return array<string, array{code: string, params?: array<string, string|int|float|bool|null>}>
     */
    private static function fieldErrorsOf(RecurrenceDraft $draft): array
    {
        try {
            new RecurrenceRules()->check($draft);
        } catch (InvalidInput $invalid) {
            return $invalid->fieldErrors;
        }

        return [];
    }
}
