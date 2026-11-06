<?php

declare(strict_types=1);

namespace App\Interface\Api\MemberPortal\Response;

use App\Workflow\MemberPortal\Model\MemberMeeting;
use App\Workflow\Organization\Model\ServiceReference;
use JsonSerializable;
use Override;

final readonly class MemberMeetingResponse implements JsonSerializable
{
    private function __construct(
        private MemberMeeting $meeting,
    ) {
    }

    public static function of(MemberMeeting $meeting): self
    {
        return new self($meeting);
    }

    /**
     * @return array{
     *     key: string,
     *     date: string,
     *     startTime: string,
     *     endTime: string|null,
     *     title: string,
     *     group: string,
     *     service: ServiceReference,
     *     location: string|null,
     *     status: string,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $time = $this->meeting->time;
        $group = $this->meeting->group;

        return [
            'key' => $this->meeting->key,
            'date' => $time->date,
            'startTime' => $time->startTime,
            'endTime' => $time->endTime,
            'title' => $group->title,
            'group' => $group->group,
            'service' => $group->service,
            'location' => $group->location,
            'status' => $this->meeting->status,
        ];
    }
}
