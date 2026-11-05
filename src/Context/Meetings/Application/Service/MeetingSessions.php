<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\Repository\RecentSessionRepositoryInterface;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionListRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\AgendaDates;
use App\Context\Meetings\Domain\ValueObject\AgendaRange;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\MarkingRules;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MeetingSessions
{
    public function __construct(
        private SessionRepositoryInterface $sessions,
        private ScheduleRepositoryInterface $schedules,
        private SessionListRepositoryInterface $lists,
        private RecentSessionRepositoryInterface $recent,
        private PlannedDates $plannedDates,
    ) {
    }

    /**
     * @return ReadableCollection<int, Session>
     */
    public function on(LocalDate $date): ReadableCollection
    {
        return $this->lists->on($date);
    }

    /**
     * @return ReadableCollection<int, Session>
     */
    public function openBetween(LocalDate $from, LocalDate $to): ReadableCollection
    {
        return $this->lists->openBetween($from, $to);
    }

    /**
     * @param list<Uuid> $excludedServiceIds
     *
     * @return ReadableCollection<int, Session>
     */
    public function latestClosed(int $limit, array $excludedServiceIds): ReadableCollection
    {
        return $this->lists->latestClosed($limit, $excludedServiceIds);
    }

    /**
     * @param positive-int $limit
     *
     * @return ReadableCollection<int, Session> oldest first
     */
    public function latestCompletedOfStage(Uuid $stageId, int $limit): ReadableCollection
    {
        return $this->recent->latestCompleted($this->schedules->listed(serviceId: null, stageId: $stageId), $limit);
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     */
    public function loadSchedulesOf(ReadableCollection $sessions): void
    {
        $this->schedules->findByIds(
            Collections::firstPerKey(
                $sessions->map(static fn (Session $session): Uuid => $session->schedule()->id()),
                keyOf: static fn (Uuid $id): string => $id->toRfc4122(),
            )->getValues(),
        );
    }

    public function upcoming(LocalDate $today, int $days, ArchivedStructure $archived): AgendaDates
    {
        $schedules = $archived->inOpenServices($this->schedules->listed(serviceId: null, stageId: null));
        $dates = $this->plannedDates->agendaOf($schedules, $archived, AgendaRange::ahead($today, $days));

        return $dates->withoutCompleted();
    }

    public function find(string $id): Session
    {
        return $this->sessions->find(Uuid::fromString($id)) ?? throw NotFound::of('session');
    }

    /**
     * @return ReadableCollection<int, SessionEvent>
     */
    public function historyOf(Session $session): ReadableCollection
    {
        return $this->sessions->historyOf($session);
    }

    /**
     * @template T of object
     *
     * @param callable(Session): T $work
     *
     * @return T
     */
    public function whileMarkable(string $sessionId, callable $work): object
    {
        return $this->sessions->withWriteLock(Uuid::fromString($sessionId), static function (Session $session) use (
            $work,
        ): object {
            MarkingRules::ensureMarkable($session->progress()->status);

            return $work($session);
        });
    }
}
