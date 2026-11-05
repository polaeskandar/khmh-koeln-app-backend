<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionNoteRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionTotalsRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\CountedSession;
use App\Context\Meetings\Domain\ValueObject\CoveredSession;
use App\Context\Meetings\Domain\ValueObject\ErasedCounts;
use App\Context\Meetings\Domain\ValueObject\MeetingNote;
use App\Context\Meetings\Domain\ValueObject\MeetingNotes;
use App\Context\Meetings\Domain\ValueObject\RosterPlace;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MeetingErasures
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private SessionTotalsRepositoryInterface $totals,
        private SessionNoteRepositoryInterface $notes,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param list<RosterPlace> $places
     * @param list<Uuid> $markedSessionIds
     *
     * @return ReadableCollection<int, Session>
     */
    public function sessionsOf(array $places, array $markedSessionIds): ReadableCollection
    {
        $today = $this->calendar->today();

        $placed = $this->schedulesWhere(self::holds(...), $places);

        $found = $this->totals->ofSchedulesOrIds($placed->getValues(), $markedSessionIds);

        return new ArrayCollection(
            Collections::firstPerKey($found, keyOf: self::sessionKey(...))
                ->filter(static fn (Session $session): bool => CountedSession::is($session, $today))
                ->getValues(),
        );
    }

    public function raise(Uuid $sessionId, ErasedCounts $share): void
    {
        $this->totals->addErased($sessionId, $share);
    }

    /**
     * @param list<RosterPlace> $places
     */
    public function notesCovering(array $places): MeetingNotes
    {
        $covering = $this->schedulesWhere(self::covers(...), $places);
        $meetings = Collections::firstPerKey($covering, keyOf: self::scheduleKey(...));
        $ids = $covering->map(static fn (Schedule $schedule): Uuid => $schedule->id())->getValues();

        $notes = new ArrayCollection($this->notes->notesOf($ids))->map(
            static fn (array $note): MeetingNote => new MeetingNote(
                $note['id'],
                $note['where'],
                $note['text'],
                $note['date'],
                $meetings->get($note['scheduleId'])?->details()->name,
            ),
        );

        $sessions = $this->totals->ofSchedulesOrIds($covering->getValues(), [])->map(CoveredSession::of(...));

        return new MeetingNotes($notes->getValues(), $sessions->getValues());
    }

    public function clearNote(string $where, string $id): void
    {
        $this->notes->clear($where, $id);
    }

    /**
     * @param callable(RosterPlace, Schedule): bool $matches
     * @param list<RosterPlace> $places
     *
     * @return ReadableCollection<int, Schedule>
     */
    private function schedulesWhere(callable $matches, array $places): ReadableCollection
    {
        return new ArrayCollection(
            $this->schedules
                ->listed(serviceId: null, stageId: null)
                ->filter(
                    static fn (Schedule $schedule): bool => !new ArrayCollection($places)
                        ->filter(static fn (RosterPlace $place): bool => $matches($place, $schedule))
                        ->isEmpty(),
                )
                ->getValues(),
        );
    }

    private static function holds(RosterPlace $place, Schedule $schedule): bool
    {
        return $place->holds($schedule->roster());
    }

    private static function covers(RosterPlace $place, Schedule $schedule): bool
    {
        return $place->isCoveredBy($schedule->roster());
    }

    private static function sessionKey(Session $session): string
    {
        return $session->id()->toRfc4122();
    }

    private static function scheduleKey(Schedule $schedule): string
    {
        return $schedule->id()->toRfc4122();
    }
}
