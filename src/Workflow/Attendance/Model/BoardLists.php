<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Collections;
use App\Library\Collections\Model\Groups;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class BoardLists
{
    private const array SHOWN = [SessionStatus::Active, SessionStatus::Completed];

    public static function areShownFor(Session $session): bool
    {
        return new ArrayCollection(self::SHOWN)->contains($session->progress()->status);
    }

    /**
     * @param ReadableCollection<int, Attendance> $current
     *
     * @return ReadableCollection<string, non-empty-list<string>>
     */
    public static function presentOf(ReadableCollection $current): ReadableCollection
    {
        return Groups::of(
            $current,
            keyOf: static fn (Attendance $mark): string => $mark->sessionId()->toRfc4122(),
            valueOf: static fn (Attendance $mark): string => $mark->personId()->toRfc4122(),
        );
    }

    /**
     * @param ReadableCollection<int, Attendance> $current
     *
     * @return ReadableCollection<non-empty-string, Attendance>
     */
    public static function byPerson(ReadableCollection $current): ReadableCollection
    {
        $keyOf = static fn (Attendance $mark): string => $mark->personId()->toRfc4122();

        return Collections::firstPerKey($current, keyOf: $keyOf);
    }

    /**
     * @param ReadableCollection<non-empty-string, Attendance> $byPerson
     *
     * @return ReadableCollection<non-empty-string, Attendance>
     */
    public static function visitorsOf(SessionTally $tally, ReadableCollection $byPerson): ReadableCollection
    {
        $roster = new ArrayCollection($tally->rosterIds);

        return $byPerson->filter(
            static fn (Attendance $mark): bool => !$roster->contains($mark->personId()->toRfc4122()),
        );
    }

    /**
     * @param ReadableCollection<int, Attendance> $current
     *
     * @return list<Uuid>
     */
    public static function personIdsOf(SessionTally $tally, ReadableCollection $current): array
    {
        $ids = new ArrayCollection([
            ...new ArrayCollection($tally->rosterIds)->map(Uuid::fromString(...))->getValues(),
            ...$current->map(static fn (Attendance $mark): Uuid => $mark->personId())->getValues(),
        ]);

        return Collections::firstPerKey($ids, keyOf: static fn (Uuid $id): string => $id->toRfc4122())->getValues();
    }

    /**
     * @param ReadableCollection<non-empty-string, Attendance> $visitors
     *
     * @return list<Uuid>
     */
    public static function visitorIdsOf(ReadableCollection $visitors): array
    {
        return $visitors->map(static fn (Attendance $mark): Uuid => $mark->personId())->getValues();
    }

    /**
     * @param ReadableCollection<non-empty-string, Attendance> $visitors
     * @param ReadableCollection<non-empty-string, Person> $people
     *
     * @return ReadableCollection<int, Person>
     */
    public static function peopleOf(ReadableCollection $visitors, ReadableCollection $people): ReadableCollection
    {
        return new ArrayCollection(
            Collections::mapPresent($visitors, static fn (Attendance $mark): ?Person => $people->get(
                $mark->personId()->toRfc4122(),
            ))->getValues(),
        );
    }
}
