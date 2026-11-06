<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Interface;

use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;

interface RecurrenceRuleInterface
{
    public function isBrokenBy(RecurrenceDraft $draft): bool;

    public function code(): string;

    public function field(): string;
}
