<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionTotalsRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\CountedSession;
use App\Context\Meetings\Domain\ValueObject\ErasedCounts;
use App\Context\Meetings\Domain\ValueObject\RosterPlace;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ErasedTotals
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private SessionTotalsRepositoryInterface $totals,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param list<RosterPlace> $places
     * @param list<Uuid> $markedSessionIds
     *
     * @return ReadableCollection<int, Session>
     */
    public function sessionsOf(array $places, array $markedSessionIds): ReadableCollection
    {
        $today = $this->calendar->today();

        $placed = $this->schedules
            ->listed(serviceId: null, stageId: null)
            ->filter(static fn (Schedule $schedule): bool => self::isPlaced($schedule, $places));

        $found = $this->totals->ofSchedulesOrIds($placed->getValues(), $markedSessionIds);

        return new ArrayCollection(
            Collections::firstPerKey($found, keyOf: self::keyOf(...))
                ->filter(static fn (Session $session): bool => CountedSession::is($session, $today))
                ->getValues(),
        );
    }

    public function raise(Uuid $sessionId, ErasedCounts $share): void
    {
        $this->totals->addErased($sessionId, $share);
    }

    /**
     * @param list<RosterPlace> $places
     */
    private static function isPlaced(Schedule $schedule, array $places): bool
    {
        return !new ArrayCollection($places)
            ->filter(static fn (RosterPlace $place): bool => $place->holds($schedule->roster()))
            ->isEmpty();
    }

    private static function keyOf(Session $session): string
    {
        return $session->id()->toRfc4122();
    }
}
