<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Context\Scheduling\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Scheduling\Domain\Repository\SessionRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\Actor;
use App\Context\Scheduling\Domain\ValueObject\ArchivedStructure;
use App\Context\Scheduling\Domain\ValueObject\GenerationResult;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use App\Platform\Doctrine\Contract\Transactions;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class SessionGenerator
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private SessionRepositoryInterface $sessions,
        private Calendar $calendar,
        private LoggerInterface $logger,
        private Transactions $transactions,
    ) {
    }

    public function generate(LocalDate $today, ArchivedStructure $archived): GenerationResult
    {
        return new ArrayCollection($this->schedules->activeIds())->reduce(fn (
            GenerationResult $sum,
            Uuid $id,
        ): GenerationResult => $sum->plus($this->safely($id, $today, $archived)), initial: new GenerationResult());
    }

    public function generateFor(Schedule $schedule, LocalDate $today, ArchivedStructure $archived): GenerationResult
    {
        return $this->forSchedule($schedule->id(), $today, $archived);
    }

    private function safely(Uuid $id, LocalDate $today, ArchivedStructure $archived): GenerationResult
    {
        try {
            return $this->forSchedule($id, $today, $archived);
        } catch (Throwable) {
            $this->transactions->recover();
            $this->logger->error('Session generation failed for a schedule.', ['scheduleId' => $id->toRfc4122()]);

            return new GenerationResult(failed: 1);
        }
    }

    private function forSchedule(Uuid $id, LocalDate $today, ArchivedStructure $archived): GenerationResult
    {
        return $this->sessions->createMissing(
            $id,
            static fn (Schedule $schedule): array => self::datesOf($schedule, $today, $archived),
            Actor::generator(),
            $this->calendar->now(),
        );
    }

    /**
     * @return list<LocalDate>
     */
    private static function datesOf(Schedule $schedule, LocalDate $today, ArchivedStructure $archived): array
    {
        if (!GeneratingSchedules::generates($schedule, $archived)) {
            return [];
        }

        return new ArrayCollection([$today, $today->addDays(1)])
            ->filter($schedule->recurrence()->occursOn(...))
            ->getValues();
    }
}
