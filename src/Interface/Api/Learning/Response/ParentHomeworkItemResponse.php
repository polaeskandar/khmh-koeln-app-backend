<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\ParentHomeworkItem;
use DateTimeImmutable;
use JsonSerializable;
use Override;

final readonly class ParentHomeworkItemResponse implements JsonSerializable
{
    public function __construct(
        private ParentHomeworkItem $item,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     lessonTitle: string,
     *     stage: string|null,
     *     dueDate: string|null,
     *     done: bool,
     *     completedAt: DateTimeImmutable|null,
     *     completedByYou: bool,
     *     completedBy: string|null,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $task = $this->item->task;
        $report = $this->item->report;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'lessonTitle' => $task->lessonTitle,
            'stage' => $task->stage,
            'dueDate' => $task->dueDate,
            'done' => $report->done,
            'completedAt' => $report->completedAt,
            'completedByYou' => $report->completedByYou,
            'completedBy' => $report->completedBy,
        ];
    }
}
