<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Attendance\Domain\Entity\Attendance;
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
        return Collections::mapPresent(new ArrayCollection($byName), static function (Uuid $id) use (
            $marks,
            $people,
            $memberships,
            $placements,
        ): ?VisitorRow {
            $person = $people->get($id->toRfc4122());
            $mark = $marks->get($id->toRfc4122());

            if ($person === null || $mark === null) {
                return null;
            }

            $own = $memberships->filter(static fn (Membership $held): bool => $held->person() === $person);

            return new VisitorRow(PersonReference::of($person), MarkReference::of($mark), $placements->groupsOf($own));
        })->getValues();
    }
}
