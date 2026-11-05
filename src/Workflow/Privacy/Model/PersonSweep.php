<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Learning\Application\Service\HomeworkCompletions;
use App\Context\Meetings\Application\Service\ErasedTotals;
use App\Context\Meetings\Domain\ValueObject\RosterPlace;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\People\Domain\ValueObject\ErasedElsewhere;
use App\Context\Privacy\Application\Service\PersonTraces;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class PersonSweep
{
    public static function of(
        ErasablePerson $person,
        ErasedTotals $totals,
        AttendanceMarks $marks,
        HomeworkCompletions $reports,
        PersonTraces $requests,
    ): ErasedElsewhere {
        $presentAt = self::presentAt($marks->ofPerson($person->id));
        $sessions = $totals->sessionsOf(self::placesOf($person), $presentAt);
        $shares = ErasedShares::of($sessions, $person, self::texts($presentAt));

        foreach ($shares as $sessionId => $share) {
            $totals->raise(Uuid::fromString($sessionId), $share);
        }

        $requests->forget($person->id);

        return new ErasedElsewhere($shares->count(), $marks->forget($person->id), $reports->forget($person->id));
    }

    /**
     * @param ReadableCollection<int, Attendance> $marks
     *
     * @return list<Uuid>
     */
    private static function presentAt(ReadableCollection $marks): array
    {
        return $marks
            ->filter(static fn (Attendance $mark): bool => $mark->isCurrent())
            ->map(static fn (Attendance $mark): Uuid => $mark->sessionId())
            ->getValues();
    }

    /**
     * @return list<RosterPlace>
     */
    private static function placesOf(ErasablePerson $person): array
    {
        return $person
            ->memberships
            ->map(
                static fn (Membership $membership): RosterPlace => new RosterPlace(
                    $membership->serviceId(),
                    $membership->stageId(),
                ),
            )
            ->getValues();
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return list<string>
     */
    private static function texts(array $ids): array
    {
        return new ArrayCollection($ids)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues();
    }
}
