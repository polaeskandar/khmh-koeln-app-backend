<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Interface\Api\Attendance\Response\OverviewCardResponse;
use App\Workflow\Meetings\Model\Dashboard;
use JsonSerializable;

final readonly class DashboardResponse
{
    /**
     * @param list<OverviewCardResponse> $sessions
     * @param list<JsonSerializable> $upcoming
     */
    public function __construct(
        public string $today,
        public array $sessions,
        public array $upcoming,
    ) {
    }

    public static function of(Dashboard $dashboard): self
    {
        return new self(
            $dashboard->today,
            OverviewCardResponse::listOf($dashboard->sessions),
            AgendaItemResponse::listOf($dashboard->upcoming),
        );
    }
}
