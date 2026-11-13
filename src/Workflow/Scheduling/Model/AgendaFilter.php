<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

final readonly class AgendaFilter
{
    public function __construct(
        public ?string $from,
        public ?string $to,
        public AgendaChoice $choice,
        public ?int $daysAhead = null,
    ) {
    }

    public static function ahead(int $days): self
    {
        return new self(from: null, to: null, choice: new AgendaChoice(), daysAhead: $days);
    }
}
