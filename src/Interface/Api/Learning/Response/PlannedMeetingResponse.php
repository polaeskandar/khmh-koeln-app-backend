<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\PlannedMeeting;
use JsonSerializable;
use Override;

final readonly class PlannedMeetingResponse implements JsonSerializable
{
    public function __construct(
        private PlannedMeeting $meeting,
    ) {
    }

    /**
     * @return array{kind: 'planned', date: string, startTime: string, title: string, createdOn: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $meeting = $this->meeting;

        return [
            'kind' => 'planned',
            'date' => $meeting->date,
            'startTime' => $meeting->startTime,
            'title' => $meeting->title,
            'createdOn' => $meeting->createdOn,
        ];
    }
}
