<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class CountedSessions
{
    private const int MAX_SESSIONS = 100;

    /**
     * @param list<string> $ids
     *
     * @return list<Uuid>
     */
    public static function idsOf(array $ids): array
    {
        if (new ArrayCollection($ids)->count() > self::MAX_SESSIONS) {
            throw InvalidInput::field('sessionIds', 'validation.tooMany', ['max' => self::MAX_SESSIONS]);
        }

        return Collections::firstPerKey(
            new ArrayCollection($ids)->map(Uuid::fromString(...)),
            keyOf: static fn (Uuid $id): string => $id->toRfc4122(),
        )->getValues();
    }

    /**
     * @param list<Uuid> $ids
     * @param ReadableCollection<non-empty-string, SessionTally> $tallies
     *
     * @return list<CountedSession>
     */
    public static function of(array $ids, ReadableCollection $tallies): array
    {
        return Collections::mapPresent(new ArrayCollection($ids), static function (Uuid $id) use (
            $tallies,
        ): ?CountedSession {
            $tally = $tallies->get($id->toRfc4122());

            return $tally === null ? null : new CountedSession($id->toRfc4122(), AttendanceCounts::of($tally->counts));
        })->getValues();
    }
}
