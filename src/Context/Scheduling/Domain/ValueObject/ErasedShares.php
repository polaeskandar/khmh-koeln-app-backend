<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\Enum\RosterMode;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ErasedShares
{
    /**
     * @param ReadableCollection<int, Session> $sessions
     * @param list<string> $rosteredIds
     * @param list<Uuid> $presentAt
     *
     * @return ReadableCollection<string, ErasedCounts>
     */
    public static function of(ReadableCollection $sessions, array $rosteredIds, array $presentAt): ReadableCollection
    {
        $rostered = new ArrayCollection($rosteredIds);
        $present = new ArrayCollection($presentAt)->map(static fn (Uuid $id): string => $id->toRfc4122());

        return Collections::firstPerKey($sessions, keyOf: self::idOf(...))->map(
            static fn (Session $session): ErasedCounts => self::shareOf($session, $rostered, $present),
        )->filter(static fn (ErasedCounts $share): bool => $share->roster > 0 || $share->present > 0);
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<int, Session>
     */
    public static function expectedOf(ReadableCollection $sessions): ReadableCollection
    {
        return $sessions->filter(self::isExpected(...));
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
        $roster = self::one(self::isExpected($session) && $rostered->contains(self::idOf($session)));
        $marked = self::one($present->contains(self::idOf($session)));

        return new ErasedCounts($roster, $marked, $roster * $marked);
    }

    private static function isExpected(Session $session): bool
    {
        return $session->plan()->rosterMode === RosterMode::Expected;
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
