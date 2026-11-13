<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Context\Scheduling\Domain\Enum\SessionStatus;
use App\Workflow\Learning\Model\SessionMeeting;
use JsonSerializable;
use Override;

final readonly class SessionMeetingResponse implements JsonSerializable
{
    public function __construct(
        private SessionMeeting $meeting,
    ) {
    }

    /**
     * @return array{
     *     kind: 'session',
     *     id: string,
     *     status: SessionStatus,
     *     date: string,
     *     startTime: string,
     *     endTime: string|null,
     *     title: string,
     *     location: string|null,
     *     cancelReason: string|null,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $meeting = $this->meeting;
        $time = $meeting->time;

        return [
            'kind' => 'session',
            'id' => $meeting->id,
            'status' => $meeting->progress->status,
            'date' => $time->date,
            'startTime' => $time->startTime,
            'endTime' => $time->endTime,
            'title' => $meeting->title,
            'location' => $meeting->location,
            'cancelReason' => $meeting->progress->cancelReason,
        ];
    }
}
