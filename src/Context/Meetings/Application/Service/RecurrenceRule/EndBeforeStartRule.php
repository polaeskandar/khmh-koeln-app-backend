<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service\RecurrenceRule;

use App\Context\Meetings\Domain\Interface\RecurrenceRuleInterface;
use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;
use Override;

final readonly class EndBeforeStartRule implements RecurrenceRuleInterface
{
    #[Override]
    public function isBrokenBy(RecurrenceDraft $draft): bool
    {
        return $draft->span->endsOn?->isBefore($draft->span->startsOn) === true;
    }

    #[Override]
    public function code(): string
    {
        return 'recurrence.endBeforeStart';
    }

    #[Override]
    public function field(): string
    {
        return 'span.endsOn';
    }
}
