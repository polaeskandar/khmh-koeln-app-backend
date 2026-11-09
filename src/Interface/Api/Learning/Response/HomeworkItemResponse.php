<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Workflow\Learning\Model\HomeworkItem;
use App\Workflow\Learning\Model\LessonOnDate;
use DateTimeImmutable;
use JsonSerializable;
use Override;

final readonly class HomeworkItemResponse implements JsonSerializable
{
    public function __construct(
        private HomeworkItem $item,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     status: HomeworkStatus,
     *     dueDate: string|null,
     *     publishedAt: DateTimeImmutable|null,
     *     lesson: LessonOnDate,
     *     counts: AudienceCountsResponse|null,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $item = $this->item;
        $task = $item->task;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'dueDate' => $task->dueDate,
            'publishedAt' => $task->publishedAt,
            'lesson' => $item->lesson,
            'counts' => $item->counts === null ? null : new AudienceCountsResponse($item->counts),
        ];
    }
}
