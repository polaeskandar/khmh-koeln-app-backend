<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Response;

use App\Context\Scheduling\Domain\Enum\RosterMode;
use App\Context\Scheduling\Domain\Enum\SessionStatus;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageReference;
use App\Workflow\Scheduling\Model\AttendanceCounts;
use App\Workflow\Scheduling\Model\SessionView;
use DateTimeImmutable;
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
     * @return array{
     *     kind: 'session',
     *     id: string,
     *     scheduleId: string|null,
     *     occurrenceDate: string,
     *     title: string,
     *     service: ServiceReference|null,
     *     stage: StageReference|null,
     *     date: string,
     *     startTime: string,
     *     endTime: string|null,
     *     startsAt: DateTimeImmutable,
     *     endsAt: DateTimeImmutable|null,
     *     location: string|null,
     *     status: SessionStatus,
     *     rosterMode: RosterMode,
     *     cancelReason: string|null,
     *     counts: AttendanceCounts|null,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['kind' => 'session', ...new SessionResponse($this->view)->jsonSerialize(), 'counts' => $this->counts];
    }
}
