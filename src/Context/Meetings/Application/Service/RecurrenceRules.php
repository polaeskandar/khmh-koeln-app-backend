<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Application\Service\RecurrenceRule\EndBeforeStartRule;
use App\Context\Meetings\Application\Service\RecurrenceRule\IntervalRule;
use App\Context\Meetings\Application\Service\RecurrenceRule\MonthlyOneDayRule;
use App\Context\Meetings\Application\Service\RecurrenceRule\MonthWeekOnlyMonthlyRule;
use App\Context\Meetings\Application\Service\RecurrenceRule\MonthWeekRule;
use App\Context\Meetings\Application\Service\RecurrenceRule\RecurrenceRuleInterface;
use App\Context\Meetings\Application\Service\RecurrenceRule\WeekdayRangeRule;
use App\Context\Meetings\Application\Service\RecurrenceRule\WeekdaysRule;
use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;
use App\Library\Problem\Exception\InvalidInput;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class RecurrenceRules
{
    private const string FIELD = 'recurrence';

    public function check(RecurrenceDraft $draft): void
    {
        $broken = new ArrayCollection(self::inOrder())
            ->filter(static fn (RecurrenceRuleInterface $rule): bool => $rule->isBrokenBy($draft))
            ->first();

        if ($broken === false) {
            return;
        }

        throw InvalidInput::field(self::FIELD . '.' . $broken->field(), $broken->code());
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
