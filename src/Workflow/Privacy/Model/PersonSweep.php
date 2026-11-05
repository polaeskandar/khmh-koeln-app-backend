<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Attendance\Application\Service\AttendanceErasures;
use App\Context\Learning\Application\Service\LearningErasures;
use App\Context\Meetings\Application\Service\MeetingErasures;
use App\Context\People\Domain\ValueObject\ErasedElsewhere;
use App\Context\Privacy\Application\Service\RequestErasures;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class PersonSweep
{
    public static function of(
        NamedMember $member,
        MeetingErasures $meetings,
        AttendanceErasures $attendance,
        LearningErasures $learning,
        RequestErasures $requests,
    ): ErasedElsewhere {
        $person = $member->person;
        $presentAt = $attendance->presentAt($person->id);
        $sessions = $meetings->sessionsOf(RosterPlaces::of($person), $presentAt);
        $shares = ErasedShares::of($sessions, $person, self::texts($presentAt));

        foreach ($shares as $sessionId => $share) {
            $meetings->raise(Uuid::fromString($sessionId), $share);
        }

        $requests->forgetPerson($person->id, $member->group);

        return new ErasedElsewhere($shares->count(), $attendance->forget($person->id), $learning->forget($person->id));
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
