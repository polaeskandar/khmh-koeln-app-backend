<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Workflow\Meetings\Model\Agenda;
use JsonSerializable;

final readonly class AgendaResponse
{
    /**
     * @param list<JsonSerializable> $items
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
        return new self($agenda->today, $agenda->from, $agenda->to, AgendaItemResponse::listOf($agenda->items));
    }
}
