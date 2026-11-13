<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\Scheduling\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Scheduling\Domain\Repository\SessionNoteRepositoryInterface;
use App\Context\Scheduling\Domain\Repository\SessionTotalsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\CountedSession;
use App\Context\Scheduling\Domain\ValueObject\ErasedShares;
use App\Context\Scheduling\Domain\ValueObject\RosterPlace;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Client\Calendar;
use App\Platform\Privacy\Contract\PersonNotes;
use App\Platform\Privacy\Contract\PersonReferences;
use App\Platform\Privacy\Model\ErasedPerson;
use App\Platform\Privacy\Model\ErasedPlace;
use App\Platform\Privacy\Model\FoundNote;
use App\Platform\Privacy\Model\NoteRef;
use App\Platform\Privacy\Model\RosterSlot;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class PersonTraces implements PersonReferences, PersonNotes
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
     * @return array<string, int>
     */
    #[Override]
    public function forgetPerson(ErasedPerson $person): array
    {
        $presentAt = $this->traces->presentAt($person->id);
        $sessions = $this->sessionsOf(self::placesOf($person), $presentAt);
        $rostered = $person->rosteredOn(ErasedShares::expectedOf($sessions)->map(self::slotOf(...))->getValues());
        $shares = ErasedShares::of($sessions, $rostered, $presentAt);

        foreach ($shares as $sessionId => $share) {
            $this->totals->addErased(Uuid::fromString($sessionId), $share);
        }

        return ['sessions' => $shares->count(), 'marks' => $this->traces->forget($person->id)];
    }

    /**
     * @return list<FoundNote>
     */
    #[Override]
    public function notesAbout(ErasedPerson $person): array
    {
        $covering = $this->schedulesWhere(static fn (
            RosterPlace $place,
            Schedule $schedule,
        ): bool => $place->isCoveredBy($schedule->roster()), self::placesOf($person));

        $ids = $covering->map(static fn (Schedule $schedule): Uuid => $schedule->id())->getValues();

        return new ArrayCollection($this->notes->notesOf($ids, $person->id))->map(
            static fn (array $note): FoundNote => new FoundNote(
                new NoteRef($note['where'], $note['id']),
                $note['text'],
                $note['date'],
                $note['meeting'],
                stageId: null,
            ),
        )->getValues();
    }

    /**
     * @param list<NoteRef> $notes
     */
    #[Override]
    public function clearNotes(array $notes): void
    {
        foreach ($notes as $note) {
            $this->notes->clear($note->where, $note->id);
        }
    }

    /**
     * @param list<RosterPlace> $places
     * @param list<Uuid> $markedSessionIds
     *
     * @return ReadableCollection<int, Session>
     */
    private function sessionsOf(array $places, array $markedSessionIds): ReadableCollection
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

    /**
     * @return list<RosterPlace>
     */
    private static function placesOf(ErasedPerson $person): array
    {
        return new ArrayCollection($person->places)->map(
            static fn (ErasedPlace $place): RosterPlace => new RosterPlace($place->serviceId, $place->stageId),
        )->getValues();
    }

    private static function slotOf(Session $session): RosterSlot
    {
        $roster = $session->schedule()->roster();

        return new RosterSlot(
            $session->id()->toRfc4122(),
            $roster->serviceId,
            $roster->stageId,
            $session->occurrenceDate(),
        );
    }
}
