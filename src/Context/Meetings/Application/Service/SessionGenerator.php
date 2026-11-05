<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\GenerationResult;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class SessionGenerator
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private SessionRepositoryInterface $sessions,
        private Calendar $calendar,
        private LoggerInterface $logger,
    ) {
    }

    public function generate(LocalDate $today, ArchivedStructure $archived): GenerationResult
    {
        return $this->generateAmong($archived->generating($this->schedules->active()), $today);
    }

    public function generateFor(Schedule $schedule, LocalDate $today, ArchivedStructure $archived): GenerationResult
    {
        return $this->generateAmong($archived->generating(new ArrayCollection([$schedule])), $today);
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     */
    private function generateAmong(ReadableCollection $schedules, LocalDate $today): GenerationResult
    {
        return $schedules->reduce(fn (
            GenerationResult $sum,
            Schedule $schedule,
        ): GenerationResult => $sum->plus($this->forSchedule($schedule, $today)), initial: new GenerationResult());
    }

    private function forSchedule(Schedule $schedule, LocalDate $today): GenerationResult
    {
        $dates = new ArrayCollection([$today, $today->addDays(1)])->filter($schedule->recurrence()->occursOn(...));

        try {
            $created = $this->sessions->createMissing(
                $schedule,
                $dates->getValues(),
                Actor::generator(),
                $this->calendar->now(),
            );
        } catch (Throwable) {
            $this->logger->error('Session generation failed for a schedule.', [
                'scheduleId' => $schedule->id()->toRfc4122(),
            ]);

            return new GenerationResult(failed: 1);
        }

        return new GenerationResult(created: $created, existing: $dates->count() - $created);
    }
}
