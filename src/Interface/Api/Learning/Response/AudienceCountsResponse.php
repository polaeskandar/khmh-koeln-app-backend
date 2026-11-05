<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\AudienceCounts;
use JsonSerializable;
use Override;

final readonly class AudienceCountsResponse implements JsonSerializable
{
    public function __construct(
        private AudienceCounts $counts,
    ) {
    }

    /**
     * @return array{
     *     assigned: int,
     *     completed: int,
     *     notCompleted: int,
     *     completedAfterDueDate: int,
     *     withoutParentAccount: int,
     *     withoutAccount: int,
     *     reportedTotal: int,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $counts = $this->counts;

        return [
            'assigned' => $counts->assigned,
            'completed' => $counts->completed,
            'notCompleted' => $counts->notCompleted(),
            'completedAfterDueDate' => $counts->completedAfterDueDate,
            'withoutParentAccount' => $counts->open->withoutParentAccount,
            'withoutAccount' => $counts->open->withoutAccount,
            'reportedTotal' => $counts->reportedTotal,
        ];
    }
}
