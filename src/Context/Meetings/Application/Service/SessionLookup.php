<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionListRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\CoveredRosters;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Model\LocalDate;
use App\Platform\Access\Model\GroupScope;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class SessionLookup
{
    public function __construct(
        private SessionRepositoryInterface $sessions,
        private ScheduleRepositoryInterface $schedules,
        private SessionListRepositoryInterface $lists,
    ) {
    }

    /**
     * @return ReadableCollection<int, Session>
     */
    public function listOn(LocalDate $date, GroupScope $scope): ReadableCollection
    {
        return CoveredSchedules::sessionsWithin($scope, $this->lists->listOn($date));
    }

    /**
     * @return ReadableCollection<int, Session>
     */
    public function listOpenBetween(LocalDate $from, LocalDate $to, GroupScope $scope): ReadableCollection
    {
        return CoveredSchedules::sessionsWithin($scope, $this->lists->openBetween($from, $to));
    }

    /**
     * @param list<Uuid> $archivedIds
     *
     * @return ReadableCollection<int, Session>
     */
    public function listLatestClosed(int $limit, array $archivedIds, GroupScope $scope): ReadableCollection
    {
        return $this->lists->latestClosed(
            $limit,
            $archivedIds,
            new CoveredRosters($scope->serviceIds(), $scope->stageIds()),
        );
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Session>
     */
    public function listByIds(array $ids, GroupScope $scope): ReadableCollection
    {
        return CoveredSchedules::sessionsWithin($scope, $this->lists->byIds($ids));
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<int, Schedule>
     */
    public function listSchedulesOf(ReadableCollection $sessions): ReadableCollection
    {
        return $this->schedules->findByIds(
            Collections::firstPerKey(
                $sessions->map(static fn (Session $session): Uuid => $session->schedule()->id()),
                keyOf: static fn (Uuid $id): string => $id->toRfc4122(),
            )->getValues(),
        );
    }

    public function get(Uuid $id): Session
    {
        return $this->sessions->find($id) ?? throw NotFound::of('session');
    }

    /**
     * @return ReadableCollection<int, SessionEvent>
     */
    public function listHistoryOf(Session $session): ReadableCollection
    {
        return $this->sessions->historyOf($session);
    }
}
