<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\People\Domain\Entity\Person;
use App\Workflow\People\Model\PersonReference;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class HistoryRow
{
    /**
     * @param list<string> $cells
     */
    public function __construct(
        public PersonReference $person,
        public array $cells,
        public int $presentCount,
    ) {
    }

    /**
     * @param list<SessionTally> $tallies
     */
    public static function of(Person $person, array $tallies): self
    {
        $cells = new ArrayCollection($tallies)->map(
            static fn (SessionTally $tally): string => self::cellOf($person, $tally),
        );

        return new self(
            PersonReference::of($person),
            $cells->getValues(),
            $cells->filter(static fn (string $cell): bool => $cell === 'present')->count(),
        );
    }

    private static function cellOf(Person $person, SessionTally $tally): string
    {
        $id = $person->id()->toRfc4122();

        if (new ArrayCollection($tally->presentIds)->contains($id)) {
            return 'present';
        }

        return new ArrayCollection($tally->rosterIds)->contains($id) ? 'notMarked' : 'notOnRoster';
    }
}
