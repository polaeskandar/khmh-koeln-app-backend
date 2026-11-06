<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class PersonRow
{
    /**
     * @param list<PlacementRow> $placements
     * @param list<PlacementRow> $pastPlacements
     */
    public function __construct(
        public PersonView $person,
        public array $placements,
        public array $pastPlacements,
    ) {
    }

    /**
     * @param ReadableCollection<int, Membership> $own
     */
    public static function of(Person $person, ReadableCollection $own, Placements $placements, LocalDate $today): self
    {
        $current = $own->filter(static fn (Membership $membership): bool => $membership->tenure()->includes($today));

        $ended = $own->filter(
            static fn (Membership $membership): bool => $membership->tenure()->endsOn?->isBefore($today) === true,
        );

        return new self(
            person: PersonView::of($person),
            placements: self::rowsOf($current, $placements),
            pastPlacements: self::rowsOf($ended, $placements),
        );
    }

    /**
     * @param ReadableCollection<int, Membership> $memberships
     *
     * @return list<PlacementRow>
     */
    private static function rowsOf(ReadableCollection $memberships, Placements $placements): array
    {
        $rowOf = static fn (Membership $membership): ?PlacementRow => PlacementRow::of($membership, $placements);

        return Collections::mapPresent($memberships, $rowOf)->getValues();
    }
}
