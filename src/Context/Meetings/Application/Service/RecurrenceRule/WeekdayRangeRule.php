<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service\RecurrenceRule;

use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;
use Doctrine\Common\Collections\ArrayCollection;
use Override;

final readonly class WeekdayRangeRule implements RecurrenceRuleInterface
{
    #[Override]
    public function isBrokenBy(RecurrenceDraft $draft): bool
    {
        $outside = new ArrayCollection($draft->weekdays)->filter(
            static fn (int $weekday): bool => $weekday < 1 || $weekday > 7,
        );

        return !$outside->isEmpty();
    }

    #[Override]
    public function code(): string
    {
        return 'recurrence.weekdayRange';
    }

    #[Override]
    public function field(): string
    {
        return 'weekdays';
    }
}
