<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service\RecurrenceRule;

use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;
use Doctrine\Common\Collections\ArrayCollection;
use Override;

final readonly class MonthWeekRule implements RecurrenceRuleInterface
{
    private const array WEEKS = [1, 2, 3, 4, -1];

    #[Override]
    public function isBrokenBy(RecurrenceDraft $draft): bool
    {
        return $draft->isMonthly() && !new ArrayCollection(self::WEEKS)->contains($draft->monthWeek);
    }

    #[Override]
    public function code(): string
    {
        return 'recurrence.monthWeek';
    }

    #[Override]
    public function field(): string
    {
        return 'monthWeek';
    }
}
