<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

final readonly class MeetingSlot
{
    public function __construct(
        private Schedule $schedule,
        public LocalDate $date,
        private ?Session $session,
        private ArchivedStructure $archived,
    ) {
    }

    public static function ofSession(Session $session): self
    {
        return new self($session->schedule(), $session->occurrenceDate(), $session, ArchivedStructure::of([]));
    }

    public function scheduleId(): Uuid
    {
        return $this->schedule->id();
    }

    public function stageId(): ?Uuid
    {
        return $this->schedule->roster()->stageId;
    }

    public function name(): string
    {
        return $this->schedule->details()->name;
    }

    public function isScheduled(): bool
    {
        $planned = $this->archived->generates($this->schedule) && $this->schedule->recurrence()->occursOn($this->date);

        return $planned || $this->session !== null;
    }

    public function isCanceled(): bool
    {
        return $this->session?->progress()->status === SessionStatus::Canceled;
    }
}
