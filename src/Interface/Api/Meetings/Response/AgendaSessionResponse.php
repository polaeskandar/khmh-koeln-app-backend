<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Workflow\Attendance\Model\AttendanceCounts;
use App\Workflow\Meetings\Model\SessionView;
use JsonSerializable;
use Override;

final readonly class AgendaSessionResponse implements JsonSerializable
{
    public function __construct(
        private SessionView $view,
        private ?AttendanceCounts $counts,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['kind' => 'session', ...new SessionResponse($this->view)->jsonSerialize(), 'counts' => $this->counts];
    }
}
