<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\AgendaDates;
use App\Context\Meetings\Domain\ValueObject\AgendaRange;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Library\Collections\Model\Collections;
use App\Workflow\People\Model\MemberRoster;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class MemberMeetingList
{
    /**
     * @param list<MemberMeeting> $items
     */
    private function __construct(
        public string $today,
        public string $from,
        public string $to,
        public array $items,
    ) {
    }

    public static function of(AgendaRange $range, AgendaDates $dates, MemberRoster $roster): self
    {
        $held = $dates->sessions->map(static fn (Session $session): MemberMeeting => MemberMeeting::of(
            PlannedDate::ofSession($session),
            $roster,
        ));

        $plannedOf = static fn (PlannedDate $date): MemberMeeting => MemberMeeting::of($date, $roster);

        $planned = new ArrayCollection($dates->planned)->map($plannedOf);

        return new self(
            $range->today->toString(),
            $range->from->toString(),
            $range->to->toString(),
            Collections::sortedBy(
                new ArrayCollection([...$held->getValues(), ...$planned->getValues()]),
                keyOf: static fn (MemberMeeting $meeting): string => $meeting->sortKey(),
            ),
        );
    }
}
