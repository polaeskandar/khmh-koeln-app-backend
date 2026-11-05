<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\HomeworkDetail;
use App\Workflow\Learning\Model\HomeworkLessonView;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageSummary;
use JsonSerializable;
use Override;

final readonly class HomeworkDetailResponse implements JsonSerializable
{
    public function __construct(
        private HomeworkDetail $detail,
    ) {
    }

    /**
     * @return array{
     *     today: string,
     *     id: string,
     *     title: string,
     *     status: string,
     *     dueDate: string|null,
     *     publishedAt: string|null,
     *     instructions: string|null,
     *     resources: list<array{label: string, url: string|null}>,
     *     minDueDate: string,
     *     lesson: HomeworkLessonView,
     *     service: ServiceReference,
     *     stage: StageSummary,
     *     audienceSize: int,
     *     counts: AudienceCountsResponse|null,
     *     can: HomeworkAbilitiesResponse,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $detail = $this->detail;
        $facts = $detail->facts;
        $can = $detail->can;

        return [
            'today' => $facts->today,
            ...$this->task(),
            'minDueDate' => $facts->minDueDate,
            'lesson' => $detail->lesson,
            'service' => $detail->stage->service,
            'stage' => $detail->stage->stage,
            'audienceSize' => $facts->audienceSize,
            'counts' => $facts->counts === null ? null : new AudienceCountsResponse($facts->counts),
            'can' => new HomeworkAbilitiesResponse($can),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     status: string,
     *     dueDate: string|null,
     *     publishedAt: string|null,
     *     instructions: string|null,
     *     resources: list<array{label: string, url: string|null}>,
     * }
     */
    private function task(): array
    {
        $task = $this->detail->task;

        return [
            'id' => $task->id,
            'title' => $task->text->title,
            'status' => $task->status,
            'dueDate' => $task->text->dueDate,
            'publishedAt' => $task->publishedAt,
            'instructions' => $task->text->instructions,
            'resources' => $task->text->resources,
        ];
    }
}
