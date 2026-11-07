<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Meetings\Domain\Entity\Attendance;
use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Collections;
use App\Workflow\Identity\Model\AccountReferences;
use App\Workflow\People\Model\PersonReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class Corrections
{
    /**
     * @param ReadableCollection<int, Attendance> $removed
     * @param ReadableCollection<non-empty-string, Person> $people
     *
     * @return list<Correction>
     */
    public static function of(
        ReadableCollection $removed,
        ReadableCollection $people,
        AccountReferences $accounts,
    ): array {
        return Collections::mapPresent($removed, static function (Attendance $mark) use (
            $people,
            $accounts,
        ): ?Correction {
            $person = $people->get($mark->personId()->toRfc4122());

            return $person === null ? null : self::correctionOf($mark, $person, $accounts);
        })->getValues();
    }

    /**
     * @param ReadableCollection<int, Attendance> $removed
     *
     * @return list<Uuid>
     */
    public static function personIdsOf(ReadableCollection $removed): array
    {
        return self::unique($removed->map(static fn (Attendance $mark): Uuid => $mark->personId())->getValues());
    }

    /**
     * @param ReadableCollection<int, Attendance> $removed
     *
     * @return list<Uuid>
     */
    public static function accountIdsOf(ReadableCollection $removed): array
    {
        $recorders = Collections::mapPresent($removed, static fn (Attendance $mark): ?Uuid => $mark->recordedBy());
        $removers = Collections::mapPresent($removed, static fn (Attendance $mark): ?Uuid => $mark->removal()->by);

        return self::unique([...$recorders->getValues(), ...$removers->getValues()]);
    }

    private static function correctionOf(Attendance $mark, Person $person, AccountReferences $accounts): Correction
    {
        $removal = $mark->removal();

        return new Correction(
            $mark->id()->toRfc4122(),
            PersonReference::of($person),
            new MarkHistory($mark->recordedAt(), $accounts->referenceOf($mark->recordedBy())),
            new MarkHistory($removal->at, $accounts->referenceOf($removal->by)),
            $removal->reason,
        );
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return list<Uuid>
     */
    private static function unique(array $ids): array
    {
        return Collections::firstPerKey(
            new ArrayCollection($ids),
            keyOf: static fn (Uuid $id): string => $id->toRfc4122(),
        )->getValues();
    }
}
