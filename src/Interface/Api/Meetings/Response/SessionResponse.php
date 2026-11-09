<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Workflow\Meetings\Model\SessionView;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageReference;
use DateTimeImmutable;
use JsonSerializable;
use Override;

final readonly class SessionResponse implements JsonSerializable
{
    public function __construct(
        private SessionView $view,
    ) {
    }

    /**
     * @return array{
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
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $details = $this->view->details;
        $roster = $this->view->roster;

        return [
            'id' => $details->id,
            'scheduleId' => $details->scheduleId,
            'occurrenceDate' => $details->occurrenceDate,
            'title' => $details->title,
            'service' => $roster->service,
            'stage' => $roster->stage,
            ...$this->timesAndState(),
        ];
    }

    /**
     * @return array{
     *     date: string,
     *     startTime: string,
     *     endTime: string|null,
     *     startsAt: DateTimeImmutable,
     *     endsAt: DateTimeImmutable|null,
     *     location: string|null,
     *     status: SessionStatus,
     *     rosterMode: RosterMode,
     *     cancelReason: string|null,
     * }
     */
    private function timesAndState(): array
    {
        $times = $this->view->times;

        return [
            'date' => $times->date,
            'startTime' => $times->startTime,
            'endTime' => $times->endTime,
            'startsAt' => $times->startsAt,
            'endsAt' => $times->endsAt,
            'location' => $this->view->details->location,
            'status' => $this->view->progress->status,
            'rosterMode' => $this->view->roster->mode,
            'cancelReason' => $this->view->progress->cancelReason,
        ];
    }
}
