<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Library\Time\Model\LocalDate;

final readonly class MeetingView
{
    /**
     * @param array<string, string|null> $members
     */
    private function __construct(
        public string $kind,
        public array $members,
    ) {
    }

    public static function none(LocalDate $date): self
    {
        return new self('none', ['date' => $date->toString()]);
    }

    public static function session(Session $session): self
    {
        $schedule = $session->schedule();

        return new self('session', [
            'id' => $session->id()->toRfc4122(),
            'status' => $session->progress()->status->value,
            'date' => $session->occurrenceDate()->toString(),
            'startTime' => $schedule->timeSlot()->startTime->toString(),
            'endTime' => $schedule->timeSlot()->endTime?->toString(),
            'title' => $schedule->details()->name,
            'location' => $schedule->details()->location,
            'cancelReason' => $session->progress()->cancelReason,
        ]);
    }

    public static function forSchedule(?Schedule $schedule, LocalDate $date): self
    {
        if ($schedule !== null && $schedule->isActive() && $schedule->recurrence()->occursOn($date)) {
            return self::planned($schedule, $date);
        }

        return new self('not-on-schedule', ['date' => $date->toString(), 'title' => $schedule?->details()->name]);
    }

    private static function planned(Schedule $schedule, LocalDate $date): self
    {
        return new self('planned', [
            'date' => $date->toString(),
            'startTime' => $schedule->timeSlot()->startTime->toString(),
            'title' => $schedule->details()->name,
            'createdOn' => $date->addDays(-1)->toString(),
        ]);
    }
}
