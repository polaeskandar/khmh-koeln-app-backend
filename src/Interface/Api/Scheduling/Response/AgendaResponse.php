<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Response;

use App\Workflow\Scheduling\Model\Agenda;
use App\Workflow\Scheduling\Model\AgendaItem;
use App\Workflow\Scheduling\Model\SessionView;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class AgendaResponse
{
    /**
     * @param list<AgendaSessionResponse|PlannedSessionResponse> $items
     */
    public function __construct(
        public string $today,
        public string $from,
        public string $to,
        public array $items,
    ) {
    }

    public static function of(Agenda $agenda): self
    {
        return new self(
            $agenda->today,
            $agenda->from,
            $agenda->to,
            new ArrayCollection($agenda->items)->map(self::itemOf(...))->getValues(),
        );
    }

    private static function itemOf(AgendaItem $item): AgendaSessionResponse|PlannedSessionResponse
    {
        $entry = $item->entry;

        return $entry instanceof SessionView
            ? new AgendaSessionResponse($entry, $item->counts)
            : new PlannedSessionResponse($entry);
    }
}
