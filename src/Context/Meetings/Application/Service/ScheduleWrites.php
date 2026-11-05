<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\MeetingDetails;
use App\Context\Meetings\Domain\ValueObject\Recurrence;
use App\Context\Meetings\Domain\ValueObject\Roster;
use App\Context\Meetings\Domain\ValueObject\ScheduleDraft;
use App\Context\Meetings\Domain\ValueObject\SchedulePlan;
use App\Context\Meetings\Domain\ValueObject\ScheduleWritten;
use App\Context\Meetings\Domain\ValueObject\TimeSlot;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Client\Calendar;
use App\Platform\Doctrine\Contract\Transactions;
use Symfony\Component\Uid\Uuid;

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

    public function plan(ScheduleDraft $draft): SchedulePlan
    {
        InvalidInput::all(
            static fn (): MeetingDetails => $draft->details->details(),
            fn (): Recurrence => $this->rules->check($draft->recurrence),
            static fn (): TimeSlot => $draft->times->slot(),
        );

        return new SchedulePlan(
            $draft->details->details(),
            $this->rules->check($draft->recurrence),
            $draft->times->slot(),
            $draft->rosterMode,
        );
    }

    public function create(
        Uuid $serviceId,
        ?Uuid $stageId,
        SchedulePlan $plan,
        ArchivedStructure $archived,
    ): ScheduleWritten {
        $roster = new Roster($serviceId, $stageId, $plan->rosterMode);

        return $this->saved(new Schedule($roster, $plan->details, $plan->recurrence, $plan->timeSlot), $archived);
    }

    public function edit(Schedule $schedule, SchedulePlan $plan, ArchivedStructure $archived): ScheduleWritten
    {
        $schedule->edit($plan->details, $plan->recurrence, $plan->timeSlot, $plan->rosterMode);

        return $this->saved($schedule, $archived);
    }

    public function activate(Schedule $schedule, ArchivedStructure $archived): ScheduleWritten
    {
        if ($schedule->isActive()) {
            return new ScheduleWritten($schedule, 0);
        }

        $schedule->activate();

        return $this->saved($schedule, $archived);
    }

    public function deactivate(Schedule $schedule): Schedule
    {
        if (!$schedule->isActive()) {
            return $schedule;
        }

        $schedule->deactivate();
        $this->schedules->save($schedule);

        return $schedule;
    }

    private function saved(Schedule $schedule, ArchivedStructure $archived): ScheduleWritten
    {
        $created = 0;
        $today = $this->calendar->today();

        $this->transactions->run(function () use ($schedule, $archived, $today, &$created): void {
            $this->schedules->save($schedule);
            $this->schedules->replanUpcoming($schedule, $today);
            $created = $this->generator->generateFor($schedule, $today, $archived)->created;
        });

        return new ScheduleWritten($schedule, $created);
    }
}
