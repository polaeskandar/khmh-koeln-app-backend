<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service\RecurrenceRule;

use App\Context\Meetings\Domain\Interface\RecurrenceRuleInterface;
use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;
use Override;

final readonly class MonthWeekOnlyMonthlyRule implements RecurrenceRuleInterface
{
    #[Override]
    public function isBrokenBy(RecurrenceDraft $draft): bool
    {
        return !$draft->isMonthly() && $draft->monthWeek !== null;
    }

    #[Override]
    public function code(): string
    {
        return 'recurrence.monthWeekOnlyMonthly';
    }

    #[Override]
    public function field(): string
    {
        return 'monthWeek';
    }
}
