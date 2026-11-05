<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionNoteRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionTotalsRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\CountedSession;
use App\Context\Meetings\Domain\ValueObject\ErasedCounts;
use App\Context\Meetings\Domain\ValueObject\MeetingNote;
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
        private PersonTraceRepositoryInterface $traces,
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

        $placed = $this->schedulesWhere(static fn (RosterPlace $place, Schedule $schedule): bool => $place->holds(
            $schedule->roster(),
        ), $places);

        $found = $this->totals->ofSchedulesOrIds($placed->getValues(), $markedSessionIds);

        return new ArrayCollection(
            Collections::firstPerKey($found, keyOf: static fn (Session $session): string => $session->id()->toRfc4122())
                ->filter(static fn (Session $session): bool => CountedSession::is($session, $today))
                ->getValues(),
        );
    }

    public function raise(Uuid $sessionId, ErasedCounts $share): void
    {
        $this->totals->addErased($sessionId, $share);
    }

    /**
     * @return list<Uuid>
     */
    public function presentAt(Uuid $personId): array
    {
        return $this->traces->presentAt($personId);
    }

    public function forget(Uuid $personId): int
    {
        return $this->traces->forget($personId);
    }

    /**
     * @param list<RosterPlace> $places
     *
     * @return list<MeetingNote>
     */
    public function notesAbout(array $places, Uuid $personId): array
    {
        $covering = $this->schedulesWhere(static fn (
            RosterPlace $place,
            Schedule $schedule,
        ): bool => $place->isCoveredBy($schedule->roster()), $places);

        $ids = $covering->map(static fn (Schedule $schedule): Uuid => $schedule->id())->getValues();

        return new ArrayCollection($this->notes->notesOf($ids, $personId))->map(
            static fn (array $note): MeetingNote => new MeetingNote(
                $note['id'],
                $note['where'],
                $note['text'],
                $note['date'],
                $note['meeting'],
            ),
        )->getValues();
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
        $wanted = new ArrayCollection($places);

        return new ArrayCollection(
            $this->schedules
                ->listed(serviceId: null, stageId: null)
                ->filter(
                    static fn (Schedule $schedule): bool => !$wanted
                        ->filter(static fn (RosterPlace $place): bool => $matches($place, $schedule))
                        ->isEmpty(),
                )
                ->getValues(),
        );
    }
}
