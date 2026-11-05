<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class PlannedDate
{
    public const string PLANNED = 'planned';

    private const array UPCOMING = [SessionStatus::Scheduled->value, SessionStatus::Active->value, self::PLANNED];

    private function __construct(
        public Schedule $schedule,
        public LocalDate $date,
        public string $status,
        public ?string $sessionId,
    ) {
    }

    public static function planned(Schedule $schedule, LocalDate $date): self
    {
        return new self($schedule, $date, self::PLANNED, sessionId: null);
    }

    public static function ofSession(Session $session): self
    {
        return new self(
            $session->schedule(),
            $session->occurrenceDate(),
            $session->progress()->status->value,
            $session->id()->toRfc4122(),
        );
    }

    public function isUpcoming(): bool
    {
        return new ArrayCollection(self::UPCOMING)->contains($this->status);
    }

    public function key(): string
    {
        return $this->schedule->id()->toRfc4122() . '|' . $this->date->toString();
    }

    public function sortKey(): string
    {
        return $this->date->toString() . ' ' . $this->schedule->timeSlot()->startTime->toString();
    }
}
