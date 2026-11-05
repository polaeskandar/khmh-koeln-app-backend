<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Attendance\Domain\ValueObject\SessionMarks;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class BoardLists
{
    private const array SHOWN = [SessionStatus::Active, SessionStatus::Completed];

    /**
     * @param list<BoardRow> $roster
     * @param list<VisitorRow> $notOnRoster
     * @param list<BoardRow> $recentlyMarked
     * @param list<Correction> $corrections
     */
    public function __construct(
        public array $roster,
        public array $notOnRoster,
        public array $recentlyMarked,
        public array $corrections,
    ) {
    }

    public static function areShownFor(Session $session): bool
    {
        return new ArrayCollection(self::SHOWN)->contains($session->progress()->status);
    }

    public static function none(): self
    {
        return new self(roster: [], notOnRoster: [], recentlyMarked: [], corrections: []);
    }

    public static function lookedUpIn(
        SessionTally $tally,
        SessionMarks $marks,
        MarkReferences $references,
        Attendees $attendees,
        StructureLookup $structure,
    ): self {
        $roster = new ArrayCollection($tally->rosterIds);
        $people = $attendees->peopleOf(self::personIdsOf($tally, $marks));

        $byPerson = Collections::firstPerKey(
            $marks->current,
            keyOf: static fn (Attendance $mark): string => $mark->personId()->toRfc4122(),
        );

        $visitors = $byPerson->filter(
            static fn (Attendance $mark): bool => !$roster->contains($mark->personId()->toRfc4122()),
        );

        return new self(
            roster: BoardRows::roster($tally->rosterIds, $people, $byPerson, $references),
            notOnRoster: VisitorRows::of($visitors, $people, $references, $attendees, $structure),
            recentlyMarked: BoardRows::recent($marks->current, $people, $references),
            corrections: BoardRows::corrections($marks->removed, $people, $references),
        );
    }

    /**
     * @return list<Uuid>
     */
    private static function personIdsOf(SessionTally $tally, SessionMarks $marks): array
    {
        $ids = new ArrayCollection([
            ...new ArrayCollection($tally->rosterIds)->map(Uuid::fromString(...))->getValues(),
            ...$marks->current->map(static fn (Attendance $mark): Uuid => $mark->personId())->getValues(),
            ...$marks->removed->map(static fn (Attendance $mark): Uuid => $mark->personId())->getValues(),
        ]);

        return Collections::firstPerKey($ids, keyOf: static fn (Uuid $id): string => $id->toRfc4122())->getValues();
    }
}
