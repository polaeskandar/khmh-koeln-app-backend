<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Meetings\Domain\Entity\Session;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\Text;
use App\Workflow\Meetings\Model\RosterReferences;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class OverviewSessions
{
    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<int, Session>
     */
    public static function unarchived(ReadableCollection $sessions, RosterReferences $references): ReadableCollection
    {
        $isOpen = static fn (Session $session): bool => self::isOpen($session, $references);

        return new ArrayCollection($sessions->filter($isOpen)->getValues());
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<int, Session>
     */
    public static function ordered(ReadableCollection $sessions, RosterReferences $references): ReadableCollection
    {
        $keyOf = static fn (Session $session): string => self::orderOf($session, $references);

        return new ArrayCollection(Collections::sortedBy($sessions, keyOf: $keyOf));
    }

    private static function isOpen(Session $session, RosterReferences $references): bool
    {
        return $references->serviceOf($session->schedule())?->archived === false;
    }

    private static function orderOf(Session $session, RosterReferences $references): string
    {
        $schedule = $session->schedule();

        return Text::format(
            '%s|%s|%s|%04d|%s',
            $session->occurrenceDate()->toString(),
            $schedule->timeSlot()->startTime->toString(),
            $references->serviceOf($schedule)->name ?? '',
            ($references->stageOf($schedule)->position ?? -1) + 1,
            $session->id()->toRfc4122(),
        )->toString();
    }
}
