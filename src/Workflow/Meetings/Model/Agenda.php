<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\ValueObject\AgendaRange;
use App\Library\Collections\Model\Orderings;
use Collator;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class Agenda
{
    private const string COLLATION = 'de';

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

    /**
     * @param list<AgendaItem> $items
     */
    public static function of(AgendaRange $range, array $items): self
    {
        $collator = new Collator(self::COLLATION);

        $keysOf = static fn (AgendaItem $item): array => [
            $item->times->startsAt->getTimestamp(),
            (string) $collator->getSortKey($item->title),
            $item->times->date,
            $item->id,
        ];

        return new self(
            today: $range->today->toString(),
            from: $range->from->toString(),
            to: $range->to->toString(),
            items: Orderings::byKeys(new ArrayCollection($items), keysOf: $keysOf),
        );
    }
}
