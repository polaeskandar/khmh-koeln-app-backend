<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Enum\SessionStatus;

final readonly class OverviewCard
{
    public function __construct(
        public SessionView $session,
        public SessionState $state,
    ) {
    }

    public function isActive(): bool
    {
        return $this->state->status === SessionStatus::Active->value;
    }
}
