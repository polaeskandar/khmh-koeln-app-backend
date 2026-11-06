<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Application\Service\RecurrenceRule\EndBeforeStartRule;
use App\Context\Meetings\Application\Service\RecurrenceRule\IntervalRule;
use App\Context\Meetings\Application\Service\RecurrenceRule\MonthlyOneDayRule;
use App\Context\Meetings\Application\Service\RecurrenceRule\MonthWeekOnlyMonthlyRule;
use App\Context\Meetings\Application\Service\RecurrenceRule\MonthWeekRule;
use App\Context\Meetings\Application\Service\RecurrenceRule\WeekdayRangeRule;
use App\Context\Meetings\Application\Service\RecurrenceRule\WeekdaysRule;
use App\Context\Meetings\Domain\Interface\RecurrenceRuleInterface;
use App\Context\Meetings\Domain\ValueObject\Recurrence;
use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class RecurrenceRules
{
    private const string FIELD = 'recurrence';

    public function check(RecurrenceDraft $draft): Recurrence
    {
        $broken = new ArrayCollection(self::inOrder())->filter(
            static fn (RecurrenceRuleInterface $rule): bool => $rule->isBrokenBy($draft),
        );

        if ($broken->isEmpty()) {
            return $draft->toRecurrence();
        }

        throw new InvalidInput(
            Collections::firstPerKey(
                $broken,
                keyOf: self::fieldOf(...),
            )->map(static fn (RecurrenceRuleInterface $rule): array => ['code' => $rule->code()])->toArray(),
        );
    }

    /**
     * @return non-empty-string
     */
    private static function fieldOf(RecurrenceRuleInterface $rule): string
    {
        return self::FIELD . '.' . $rule->field();
    }

    /**
     * @return list<RecurrenceRuleInterface>
     */
    private static function inOrder(): array
    {
        return [
            new IntervalRule(),
            new WeekdaysRule(),
            new WeekdayRangeRule(),
            new MonthlyOneDayRule(),
            new MonthWeekRule(),
            new MonthWeekOnlyMonthlyRule(),
            new EndBeforeStartRule(),
        ];
    }
}
