<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service\RecurrenceRule;

use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;
use Doctrine\Common\Collections\ArrayCollection;
use Override;

final readonly class MonthlyOneDayRule implements RecurrenceRuleInterface
{
    #[Override]
    public function isBrokenBy(RecurrenceDraft $draft): bool
    {
        return $draft->isMonthly() && new ArrayCollection($draft->weekdays)->count() !== 1;
    }

    #[Override]
    public function code(): string
    {
        return 'recurrence.monthlyOneDay';
    }

    #[Override]
    public function field(): string
    {
        return 'weekdays';
    }
}
