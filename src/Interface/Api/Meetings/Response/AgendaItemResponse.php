<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Workflow\Meetings\Model\AgendaItem;
use App\Workflow\Meetings\Model\SessionView;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

final readonly class AgendaItemResponse
{
    /**
     * @param list<AgendaItem> $items
     *
     * @return list<JsonSerializable>
     */
    public static function listOf(array $items): array
    {
        return new ArrayCollection($items)->map(self::of(...))->getValues();
    }

    private static function of(AgendaItem $item): JsonSerializable
    {
        $entry = $item->entry;

        return $entry instanceof SessionView
            ? new AgendaSessionResponse($entry, $item->counts)
            : new PlannedSessionResponse($entry);
    }
}
