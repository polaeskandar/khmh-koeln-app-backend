<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Collections;
use App\Workflow\People\Model\PersonReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class BoardRows
{
    private const int RECENT = 6;

    /**
     * @param list<non-empty-string> $rosterIds
     * @param ReadableCollection<string, Person> $people
     * @param ReadableCollection<non-empty-string, Attendance> $byPerson
     *
     * @return list<BoardRow>
     */
    public static function roster(
        array $rosterIds,
        ReadableCollection $people,
        ReadableCollection $byPerson,
        MarkReferences $references,
    ): array {
        $onRoster = Collections::mapPresent(new ArrayCollection($rosterIds), $people->get(...));

        return $onRoster->map(
            static fn (Person $person): BoardRow => new BoardRow(
                PersonReference::of($person),
                $references->markOf($byPerson->get($person->id()->toRfc4122())),
            ),
        )->getValues();
    }

    /**
     * @param ReadableCollection<int, Attendance> $current
     * @param ReadableCollection<string, Person> $people
     *
     * @return list<BoardRow>
     */
    public static function recent(
        ReadableCollection $current,
        ReadableCollection $people,
        MarkReferences $references,
    ): array {
        $newest = new ArrayCollection($current->getValues())->matching(
            Criteria::create()->orderBy(['recordedAt' => Order::Descending])->setMaxResults(self::RECENT),
        );

        return Collections::mapPresent($newest, static function (Attendance $mark) use (
            $people,
            $references,
        ): ?BoardRow {
            $person = $people->get($mark->personId()->toRfc4122());

            return $person === null ? null : new BoardRow(PersonReference::of($person), $references->mark($mark));
        })->getValues();
    }

    /**
     * @param ReadableCollection<int, Attendance> $removed
     * @param ReadableCollection<string, Person> $people
     *
     * @return list<Correction>
     */
    public static function corrections(
        ReadableCollection $removed,
        ReadableCollection $people,
        MarkReferences $references,
    ): array {
        return Collections::mapPresent($removed, static function (Attendance $mark) use (
            $people,
            $references,
        ): ?Correction {
            $person = $people->get($mark->personId()->toRfc4122());

            return $person === null ? null : $references->correction($mark, PersonReference::of($person));
        })->getValues();
    }
}
