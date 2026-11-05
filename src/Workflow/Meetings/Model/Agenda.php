<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\AgendaDates;
use App\Context\Meetings\Domain\ValueObject\AgendaRange;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class Agenda
{
    /**
     * @param list<AgendaItem> $items
     */
    public function __construct(
        public string $today,
        public string $from,
        public string $to,
        public array $items,
    ) {
    }

    public static function of(
        AgendaRange $range,
        AgendaDates $dates,
        RosterReferences $references,
        Calendar $calendar,
        SessionAbilities $can,
    ): self {
        $items = new ArrayCollection([
            ...self::sessionItemsOf($dates, $references, $calendar, $can),
            ...self::plannedItemsOf($dates, $references, $calendar, $can),
        ]);

        return new self(
            today: $range->today->toString(),
            from: $range->from->toString(),
            to: $range->to->toString(),
            items: Collections::sortedBy($items, keyOf: static fn (AgendaItem $item): string => $item->sortKey),
        );
    }

    /**
     * @return list<AgendaItem>
     */
    private static function sessionItemsOf(
        AgendaDates $dates,
        RosterReferences $references,
        Calendar $calendar,
        SessionAbilities $can,
    ): array {
        $itemOf = static fn (Session $session): AgendaItem => AgendaItem::session(SessionView::with(
            $session,
            $references,
            $calendar,
            $can,
        ));

        return $dates->sessions->map($itemOf)->getValues();
    }

    /**
     * @return list<AgendaItem>
     */
    private static function plannedItemsOf(
        AgendaDates $dates,
        RosterReferences $references,
        Calendar $calendar,
        SessionAbilities $can,
    ): array {
        $itemOf = static fn (PlannedDate $date): AgendaItem => AgendaItem::planned(PlannedSession::of(
            $date,
            $references,
            $calendar,
            $can,
        ));

        return new ArrayCollection($dates->planned)->map($itemOf)->getValues();
    }
}
