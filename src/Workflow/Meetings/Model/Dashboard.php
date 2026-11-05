<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Workflow\Attendance\Model\OverviewCard;

final readonly class Dashboard
{
    /**
     * @param list<OverviewCard> $sessions
     * @param list<AgendaItem> $upcoming
     */
    public function __construct(
        public string $today,
        public array $sessions,
        public array $upcoming,
    ) {
    }
}
