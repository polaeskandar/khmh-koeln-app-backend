<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\People\Domain\Entity\Person;
use App\Context\Scheduling\Domain\Entity\Attendance;
use App\Library\Collections\Model\Collections;
use App\Workflow\People\Model\PersonReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class BoardRows
{
    private const int RECENT = 6;

    /**
     * @param list<string> $rosterIds
     * @param ReadableCollection<non-empty-string, Person> $people
     * @param ReadableCollection<non-empty-string, Attendance> $byPerson
     *
     * @return list<BoardRow>
     */
    public static function roster(array $rosterIds, ReadableCollection $people, ReadableCollection $byPerson): array
    {
        return Collections::mapPresent(new ArrayCollection($rosterIds), static function (string $id) use (
            $people,
            $byPerson,
        ): ?BoardRow {
            $key = Uuid::fromString($id)->toRfc4122();

            return self::rowOf($people->get($key), $byPerson->get($key));
        })->getValues();
    }

    /**
     * @param ReadableCollection<int, Attendance> $current
     * @param ReadableCollection<non-empty-string, Person> $people
     *
     * @return list<BoardRow>
     */
    public static function recent(ReadableCollection $current, ReadableCollection $people): array
    {
        $newest = new ArrayCollection($current->slice(offset: 0, length: self::RECENT));

        return Collections::mapPresent($newest, static fn (Attendance $mark): ?BoardRow => self::rowOf(
            $people->get($mark->personId()->toRfc4122()),
            $mark,
        ))->getValues();
    }

    private static function rowOf(?Person $person, ?Attendance $mark): ?BoardRow
    {
        $reference = $mark === null ? null : MarkReference::of($mark);

        return $person === null ? null : new BoardRow(PersonReference::of($person), $reference);
    }
}
