<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\MarkingRules;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MeetingSessions
{
    public function __construct(
        private SessionRepositoryInterface $sessions,
        private ScheduleRepositoryInterface $schedules,
    ) {
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
