<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Library\Time\Model\LocalDate;
use DateTimeImmutable;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface SessionRepositoryInterface
{
    public function find(Uuid $id): ?Session;

    /**
     * @return ReadableCollection<int, SessionEvent>
     */
    public function historyOf(Session $session): ReadableCollection;

    /**
     * @param list<LocalDate> $dates
     */
    public function createMissing(Schedule $schedule, array $dates, Actor $actor, DateTimeImmutable $at): int;

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     *
     * @return ReadableCollection<int, Session>
     */
    public function ofSchedulesBetween(
        ReadableCollection $schedules,
        LocalDate $from,
        LocalDate $to,
    ): ReadableCollection;

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     *
     * @return ReadableCollection<array-key, int>
     */
    public function countsBySchedule(ReadableCollection $schedules): ReadableCollection;
}
