<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service\RecurrenceRule;

use App\Context\Meetings\Domain\Interface\RecurrenceRuleInterface;
use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;
use Override;

final readonly class WeekdaysRule implements RecurrenceRuleInterface
{
    #[Override]
    public function isBrokenBy(RecurrenceDraft $draft): bool
    {
        return $draft->weekdays === [];
    }

    #[Override]
    public function code(): string
    {
        return 'recurrence.weekdays';
    }

    #[Override]
    public function field(): string
    {
        return 'weekdays';
    }
}
