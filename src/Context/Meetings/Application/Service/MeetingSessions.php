<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionListRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
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
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Session>
     */
    public function byIds(array $ids): ReadableCollection
    {
        return $this->lists->byIds($ids);
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<int, Schedule>
     */
    public function schedulesOf(ReadableCollection $sessions): ReadableCollection
    {
        return $this->schedules->findByIds(
            Collections::firstPerKey(
                $sessions->map(static fn (Session $session): Uuid => $session->schedule()->id()),
                keyOf: static fn (Uuid $id): string => $id->toRfc4122(),
            )->getValues(),
        );
    }

    public function find(string $id): Session
    {
        $session = Uuid::isValid($id) ? $this->sessions->find(Uuid::fromString($id)) : null;

        return $session ?? throw NotFound::of('session');
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
        $id = Uuid::isValid($sessionId) ? Uuid::fromString($sessionId) : throw NotFound::of('session');

        return $this->sessions->withWriteLock($id, static function (Session $session) use ($work): object {
            MarkingRules::ensureMarkable($session->progress()->status);

            return $work($session);
        });
    }
}
