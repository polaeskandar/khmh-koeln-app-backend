<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service\RecurrenceRule;

use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;
use Override;

final readonly class IntervalRule implements RecurrenceRuleInterface
{
    private const int MAX = 52;

    #[Override]
    public function isBrokenBy(RecurrenceDraft $draft): bool
    {
        $interval = $draft->interval ?? 0;

        return $interval < 1 || $interval > self::MAX;
    }

    #[Override]
    public function code(): string
    {
        return 'recurrence.interval';
    }

    #[Override]
    public function field(): string
    {
        return 'interval';
    }
}
