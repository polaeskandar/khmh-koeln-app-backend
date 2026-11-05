<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Context\Meetings\Domain\ValueObject\ErasedCounts;
use App\Context\Meetings\Domain\ValueObject\RosterPlace;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ErasedShares
{
    /**
     * @param ReadableCollection<int, Session> $sessions
     * @param list<string> $rosteredKeys
     * @param list<Uuid> $presentAt
     *
     * @return ReadableCollection<string, ErasedCounts>
     */
    public static function of(ReadableCollection $sessions, array $rosteredKeys, array $presentAt): ReadableCollection
    {
        $rostered = new ArrayCollection($rosteredKeys);
        $present = new ArrayCollection($presentAt)->map(static fn (Uuid $id): string => $id->toRfc4122());

        return Collections::firstPerKey($sessions, keyOf: self::idOf(...))->map(
            static fn (Session $session): ErasedCounts => self::shareOf($session, $rostered, $present),
        )->filter(static fn (ErasedCounts $share): bool => $share->roster > 0 || $share->present > 0);
    }

    /**
     * @return list<RosterPlace>
     */
    public static function placesOf(ErasablePerson $person): array
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
    public static function expectedKeysOf(ReadableCollection $sessions): array
    {
        return $sessions
            ->filter(self::isExpected(...))
            ->map(self::keyOf(...))
            ->getValues();
    }

    /**
     * @param ReadableCollection<int, string> $rostered
     * @param ReadableCollection<int, string> $present
     */
    private static function shareOf(
        Session $session,
        ReadableCollection $rostered,
        ReadableCollection $present,
    ): ErasedCounts {
        $roster = self::one(self::isExpected($session) && $rostered->contains(self::keyOf($session)->id()));
        $marked = self::one($present->contains(self::idOf($session)));

        return new ErasedCounts($roster, $marked, $roster * $marked);
    }

    private static function isExpected(Session $session): bool
    {
        return $session->plan()->rosterMode === RosterMode::Expected;
    }

    private static function keyOf(Session $session): RosterKey
    {
        $roster = $session->schedule()->roster();

        return new RosterKey($roster->serviceId, $roster->stageId, $session->occurrenceDate());
    }

    private static function one(bool $counted): int
    {
        return match ($counted) {
            true => 1,
            false => 0,
        };
    }

    private static function idOf(Session $session): string
    {
        return $session->id()->toRfc4122();
    }
}
