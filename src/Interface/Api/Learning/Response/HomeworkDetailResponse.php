<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\HomeworkDetail;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageSummary;
use DateTimeImmutable;
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
     *     publishedAt: DateTimeImmutable|null,
     *     instructions: string|null,
     *     resources: list<array{label: string, url: string|null}>,
     *     version: int,
     *     minDueDate: string,
     *     lesson: array<string, string|bool|null>,
     *     service: ServiceReference,
     *     stage: StageSummary,
     *     archived: bool,
     *     audienceSize: int,
     *     counts: AudienceCountsResponse|null,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $detail = $this->detail;
        $facts = $detail->facts;

        return [
            'today' => $facts->today,
            ...$this->task(),
            'minDueDate' => $facts->minDueDate,
            'lesson' => $this->lesson(),
            'service' => $detail->stage->service,
            'stage' => $detail->stage->stage,
            'archived' => $detail->stage->archived,
            'audienceSize' => $facts->audienceSize,
            'counts' => $facts->counts === null ? null : new AudienceCountsResponse($facts->counts),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     teachingDate: string,
     *     passage: string|null,
     *     memoryVerse: string|null,
     *     archived: bool,
     * }
     */
    private function lesson(): array
    {
        $lesson = $this->detail->lesson;

        return [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'teachingDate' => $lesson->teachingDate,
            'passage' => $lesson->passage,
            'memoryVerse' => $lesson->memoryVerse,
            'archived' => $this->detail->facts->lessonArchived,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     status: string,
     *     dueDate: string|null,
     *     publishedAt: DateTimeImmutable|null,
     *     instructions: string|null,
     *     resources: list<array{label: string, url: string|null}>,
     *     version: int,
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
            'version' => $task->version,
        ];
    }
}
