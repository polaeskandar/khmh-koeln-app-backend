<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Workflow\Meetings\Model\SessionView;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageSummary;
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
     *     oneOff: bool,
     *     occurrenceDate: string,
     *     title: string,
     *     service: ServiceReference|null,
     *     stage: StageSummary|null,
     *     date: string,
     *     startTime: string,
     *     endTime: string|null,
     *     startsAt: string,
     *     endsAt: string|null,
     *     location: string|null,
     *     status: string,
     *     rosterMode: string,
     *     cancelReason: string|null,
     *     can: array{manage: bool, record: bool, reopen: bool},
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
            'oneOff' => $details->isOneOff(),
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
     *     startsAt: string,
     *     endsAt: string|null,
     *     location: string|null,
     *     status: string,
     *     rosterMode: string,
     *     cancelReason: string|null,
     *     can: array{manage: bool, record: bool, reopen: bool},
     * }
     */
    private function timesAndState(): array
    {
        $times = $this->view->times;
        $can = $this->view->can;

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
            'can' => ['manage' => $can->manage, 'record' => $can->record, 'reopen' => $can->reopen],
        ];
    }
}
