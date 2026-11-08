<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Library\Collections\Model\Collections;
use App\Library\Collections\Model\Orderings;
use Collator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class OverviewSessions
{
    private const string COLLATION = 'de';

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<int, Session>
     */
    public static function unarchived(ReadableCollection $sessions, ArchivedStructure $archived): ReadableCollection
    {
        $isOpen = static fn (Session $session): bool => !$archived->holds($session->schedule()->roster());

        return new ArrayCollection($sessions->filter($isOpen)->getValues());
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<int, Session>
     */
    public static function ordered(ReadableCollection $sessions, RosterReferences $references): ReadableCollection
    {
        $collator = new Collator(self::COLLATION);
        $keysOf = static fn (Session $session): array => self::orderOf($session, $references, $collator);

        return new ArrayCollection(Orderings::byKeys($sessions, keysOf: $keysOf));
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<int, Schedule>
     */
    public static function schedulesOf(ReadableCollection $sessions): ReadableCollection
    {
        return $sessions->map(static fn (Session $session): Schedule => $session->schedule());
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return list<Uuid>
     */
    public static function closerIdsOf(ReadableCollection $sessions): array
    {
        return Collections::mapPresent(
            $sessions,
            static fn (Session $session): ?Uuid => $session->progress()->closed->by,
        )->getValues();
    }

    /**
     * @return list<string|int>
     */
    private static function orderOf(Session $session, RosterReferences $references, Collator $collator): array
    {
        $schedule = $session->schedule();

        return [
            $session->occurrenceDate()->toString(),
            $session->plan()->timeSlot->startTime->toString(),
            (string) $collator->getSortKey($references->serviceOf($schedule)->name ?? ''),
            ($references->stageOf($schedule)->position ?? -1) + 1,
            $session->id()->toRfc4122(),
        ];
    }
}
