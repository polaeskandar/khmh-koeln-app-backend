<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Workflow\Meetings\Model\SessionState;
use App\Workflow\Meetings\Model\SessionView;

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
