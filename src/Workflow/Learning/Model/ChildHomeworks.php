<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\PupilTasks;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\PeopleWithGroups;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ChildHomeworks
{
    /**
     * @param list<PupilTasks> $tasks
     *
     * @return list<ChildHomework>
     */
    public static function of(PeopleWithGroups $family, array $tasks): array
    {
        $byPupil = Collections::firstPerKey(new ArrayCollection($tasks), keyOf: self::pupilKey(...));

        return Collections::mapPresent(
            new ArrayCollection($family->people),
            static fn (Person $child): ?ChildHomework => self::childOf($family, $child, $byPupil),
        )->getValues();
    }

    /**
     * @param ReadableCollection<string, PupilTasks> $tasks
     */
    private static function childOf(PeopleWithGroups $family, Person $child, ReadableCollection $tasks): ?ChildHomework
    {
        $own = $tasks->get($child->id()->toRfc4122());

        return $own === null ? null : new ChildHomework($child, $family->membershipsOf($child), $own);
    }

    private static function pupilKey(PupilTasks $tasks): string
    {
        return $tasks->personId->toRfc4122();
    }
}
