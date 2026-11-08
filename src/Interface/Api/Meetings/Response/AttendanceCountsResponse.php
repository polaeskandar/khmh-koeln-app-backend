<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Workflow\Meetings\Model\CountedSession;
use JsonSerializable;
use Override;

final readonly class AttendanceCountsResponse implements JsonSerializable
{
    /**
     * @param list<CountedSession> $items
     */
    public function __construct(
        private array $items,
    ) {
    }

    /**
     * @return array{items: list<CountedSession>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['items' => $this->items];
    }
}
