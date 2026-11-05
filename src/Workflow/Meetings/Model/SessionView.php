<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Session;
use DateTimeImmutable;

final readonly class SessionView
{
    public function __construct(
        public SessionDetails $details,
        public ScheduleRoster $roster,
        public SessionTimes $times,
        public SessionProgress $progress,
    ) {
    }

    public static function of(
        Session $session,
        RosterReferences $references,
        DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
    ): self {
        return new self(
            SessionDetails::of($session),
            ScheduleRoster::ofSession($session, $references),
            SessionTimes::of($session, $startsAt, $endsAt),
            SessionProgress::of($session),
        );
    }

    public function withoutCancelReason(): self
    {
        return new self($this->details, $this->roster, $this->times, $this->progress->withoutCancelReason());
    }
}
