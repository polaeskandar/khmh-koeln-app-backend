<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\ErasedCounts;
use App\Library\Collections\Model\Collections;
use App\Workflow\Attendance\Model\SessionRoster;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ErasedShares
{
    /**
     * @param ReadableCollection<int, Session> $sessions
     * @param list<string> $rosteredKeys
     * @param list<string> $presentAt
     *
     * @return ReadableCollection<string, ErasedCounts>
     */
    public static function of(ReadableCollection $sessions, array $rosteredKeys, array $presentAt): ReadableCollection
    {
        $rostered = new ArrayCollection($rosteredKeys);
        $present = new ArrayCollection($presentAt);

        return Collections::firstPerKey($sessions, keyOf: self::idOf(...))->map(
            static fn (Session $session): ErasedCounts => self::shareOf($session, $rostered, $present),
        )->filter(static fn (ErasedCounts $share): bool => $share->roster > 0 || $share->present > 0);
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
        $onRoster = SessionRoster::isExpected($session) && $rostered->contains(SessionRoster::keyOf($session)->id());
        $roster = self::one($onRoster);
        $marked = self::one($present->contains(self::idOf($session)));

        return new ErasedCounts($roster, $marked, $roster * $marked);
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
