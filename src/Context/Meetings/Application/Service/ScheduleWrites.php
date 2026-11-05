<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\ScheduleDraft;
use App\Context\Meetings\Domain\ValueObject\ScheduleEdit;
use App\Context\Meetings\Domain\ValueObject\ScheduleWritten;
use App\Library\Time\Client\Calendar;
use App\Platform\Doctrine\Contract\Transactions;

final readonly class ScheduleWrites
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private SessionGenerator $generator,
        private RecurrenceRules $rules,
        private Transactions $transactions,
        private Calendar $calendar,
    ) {
    }

    public function create(ScheduleDraft $draft, ArchivedStructure $archived): ScheduleWritten
    {
        $this->rules->check($draft->recurrence);

        return $this->saved(
            new Schedule(
                $draft->roster,
                $draft->details->merged(stored: null),
                $draft->recurrence->toRecurrence(),
                $draft->times->merged(stored: null),
            ),
            $archived,
        );
    }

    public function edit(Schedule $schedule, ScheduleEdit $edit, ArchivedStructure $archived): ScheduleWritten
    {
        $recurrence = $edit->recurrence;

        if ($recurrence !== null) {
            $this->rules->check($recurrence);
        }

        $schedule->edit(
            $edit->details->merged($schedule->details()),
            $recurrence?->toRecurrence() ?? $schedule->recurrence(),
            $edit->times->merged($schedule->timeSlot()),
            $edit->rosterMode ?? $schedule->roster()->mode,
        );

        return $this->saved($schedule, $archived);
    }

    public function activate(Schedule $schedule, ArchivedStructure $archived): ScheduleWritten
    {
        return $schedule->activate() ? $this->saved($schedule, $archived) : new ScheduleWritten($schedule, 0);
    }

    public function deactivate(Schedule $schedule): Schedule
    {
        if ($schedule->deactivate()) {
            $this->schedules->save($schedule);
        }

        return $schedule;
    }

    private function saved(Schedule $schedule, ArchivedStructure $archived): ScheduleWritten
    {
        $created = 0;

        $this->transactions->run(function () use ($schedule, $archived, &$created): void {
            $this->schedules->save($schedule);
            $created = $this->generator->generateFor($schedule, $this->calendar->today(), $archived)->created;
        });

        return new ScheduleWritten($schedule, $created);
    }
}
