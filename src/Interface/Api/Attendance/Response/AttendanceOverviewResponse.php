<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance\Response;

use App\Workflow\Attendance\Model\AttendanceOverview;
use JsonSerializable;
use Override;

final readonly class AttendanceOverviewResponse implements JsonSerializable
{
    public function __construct(
        private AttendanceOverview $overview,
    ) {
    }

    /**
     * @return array{
     *     today: string,
     *     sessions: list<OverviewCardResponse>,
     *     earlier: list<OverviewCardResponse>,
     *     closed: list<OverviewCardResponse>,
     *     openCount: int,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'today' => $this->overview->today,
            'sessions' => OverviewCardResponse::listOf($this->overview->sessions),
            'earlier' => OverviewCardResponse::listOf($this->overview->earlier),
            'closed' => OverviewCardResponse::listOf($this->overview->closed),
            'openCount' => $this->overview->openCount,
        ];
    }
}
