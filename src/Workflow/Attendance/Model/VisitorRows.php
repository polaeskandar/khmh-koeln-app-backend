<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Collections;
use App\Workflow\People\Model\PersonReference;
use App\Workflow\People\Model\Placements;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class VisitorRows
{
    /**
     * @param ReadableCollection<non-empty-string, Attendance> $marks
     * @param ReadableCollection<string, Person> $people
     *
     * @return list<VisitorRow>
     */
    public static function of(
        ReadableCollection $marks,
        ReadableCollection $people,
        MarkReferences $references,
        Attendees $attendees,
        StructureLookup $structure,
    ): array {
        $ordered = new ArrayCollection(
            $attendees->byName($marks->map(static fn (Attendance $mark): Uuid => $mark->personId())->getValues()),
        );

        $visitors = Collections::mapPresent($ordered, static fn (Uuid $id): ?Person => $people->get($id->toRfc4122()));
        $memberships = $attendees->currentMembershipsOf($visitors);
        $placements = Placements::lookedUpIn($structure, $memberships);

        return Collections::mapPresent($visitors, static function (Person $person) use (
            $marks,
            $memberships,
            $placements,
            $references,
        ): ?VisitorRow {
            $mark = $marks->get($person->id()->toRfc4122());
            $own = $memberships->filter(static fn (Membership $held): bool => $held->person() === $person);

            return $mark === null
                ? null
                : new VisitorRow(PersonReference::of($person), $references->mark($mark), $placements->groupsOf($own));
        })->getValues();
    }
}
