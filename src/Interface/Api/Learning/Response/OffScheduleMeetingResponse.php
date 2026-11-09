<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\OffScheduleMeeting;
use JsonSerializable;
use Override;

final readonly class OffScheduleMeetingResponse implements JsonSerializable
{
    public function __construct(
        private OffScheduleMeeting $meeting,
    ) {
    }

    /**
     * @return array{kind: 'not-on-schedule', date: string, title: string|null}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['kind' => 'not-on-schedule', 'date' => $this->meeting->date, 'title' => $this->meeting->title];
    }
}
