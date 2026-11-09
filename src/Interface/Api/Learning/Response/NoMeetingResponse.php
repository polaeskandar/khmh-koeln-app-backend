<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\NoMeeting;
use JsonSerializable;
use Override;

final readonly class NoMeetingResponse implements JsonSerializable
{
    public function __construct(
        private NoMeeting $meeting,
    ) {
    }

    /**
     * @return array{kind: 'none', date: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['kind' => 'none', 'date' => $this->meeting->date];
    }
}
