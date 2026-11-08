<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Attendance;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Collections;
use App\Library\Collections\Model\Groups;
use App\Workflow\People\Model\PersonReference;
use App\Workflow\People\Model\Placements;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class VisitorRows
{
    /**
     * @param ReadableCollection<non-empty-string, Attendance> $marks
     * @param list<Uuid> $byName
     * @param ReadableCollection<non-empty-string, Person> $people
     * @param ReadableCollection<int, Membership> $memberships
     *
     * @return list<VisitorRow>
     */
    public static function of(
        ReadableCollection $marks,
        array $byName,
        ReadableCollection $people,
        ReadableCollection $memberships,
        Placements $placements,
    ): array {
        $held = Groups::of(
            $memberships,
            keyOf: static fn (Membership $membership): string => $membership->person()->id()->toRfc4122(),
            valueOf: static fn (Membership $membership): Membership => $membership,
        );

        return Collections::mapPresent(new ArrayCollection($byName), static function (Uuid $id) use (
            $marks,
            $people,
            $held,
            $placements,
        ): ?VisitorRow {
            $person = $people->get($id->toRfc4122());
            $mark = $marks->get($id->toRfc4122());

            if ($person === null || $mark === null) {
                return null;
            }

            $own = new ArrayCollection($held->get($id->toRfc4122()) ?? []);

            return new VisitorRow(PersonReference::of($person), MarkReference::of($mark), $placements->groupsOf($own));
        })->getValues();
    }
}
