<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\ValueObject\AgendaDates;
use App\Context\Meetings\Domain\ValueObject\AgendaRange;
use App\Library\Collections\Model\Collections;
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

    public static function of(AgendaRange $range, AgendaDates $dates, AgendaViews $views): self
    {
        return new self(
            today: $range->today->toString(),
            from: $range->from->toString(),
            to: $range->to->toString(),
            items: self::itemsOf($dates, $views),
        );
    }

    /**
     * @return list<AgendaItem>
     */
    public static function itemsOf(AgendaDates $dates, AgendaViews $views): array
    {
        $items = new ArrayCollection([
            ...$dates->sessions->map($views->sessionItem(...))->getValues(),
            ...new ArrayCollection($dates->planned)->map($views->plannedItem(...))->getValues(),
        ]);

        return Collections::sortedBy($items, keyOf: static fn (AgendaItem $item): string => $item->sortKey);
    }
}
