<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Learning\Application\Service\HomeworkCompletions;
use App\Context\Meetings\Application\Service\ErasedTotals;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\RosterPlace;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Workflow\Attendance\Model\SessionRoster;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class PersonSweep
{
    public static function of(
        string $personId,
        PersonErasures $people,
        ErasedTotals $totals,
        AttendanceMarks $marks,
        HomeworkCompletions $reports,
    ): PersonErasureCounts {
        $person = $people->erasable($personId);
        $presentAt = self::presentAt($marks->ofPerson($person->id));
        $sessions = $totals->sessionsOf(self::placesOf($person), $presentAt);
        $rostered = $people->rosteredOn($person->id, self::expectedKeysOf($sessions));
        $shares = ErasedShares::of($sessions, $rostered, self::texts($presentAt));

        foreach ($shares as $sessionId => $share) {
            $totals->raise(Uuid::fromString($sessionId), $share);
        }

        $attendance = $marks->forget($person->id);
        $completions = $reports->forget($person->id);
        $forgotten = $people->forget($person->id);

        return new PersonErasureCounts(
            $forgotten->memberships,
            $attendance,
            $completions,
            $forgotten->guardianLinks,
            $shares->count(),
        );
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
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return list<RosterKey>
     */
    private static function expectedKeysOf(ReadableCollection $sessions): array
    {
        return $sessions
            ->filter(SessionRoster::isExpected(...))
            ->map(SessionRoster::keyOf(...))
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
