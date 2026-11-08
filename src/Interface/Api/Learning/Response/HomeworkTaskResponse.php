<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\HomeworkLessonView;
use App\Workflow\Learning\Model\HomeworkTextView;
use App\Workflow\Learning\Model\ParentHomeworkItem;
use DateTimeImmutable;
use JsonSerializable;
use Override;

final readonly class HomeworkTaskResponse implements JsonSerializable
{
    public function __construct(
        private ParentHomeworkItem $item,
        private HomeworkTextView $text,
        private HomeworkLessonView $lesson,
    ) {
    }

    /**
     * @return array{
     *     task: array{
     *         id: string,
     *         title: string,
     *         lessonTitle: string,
     *         stage: string|null,
     *         dueDate: string|null,
     *         done: bool,
     *         completedAt: DateTimeImmutable|null,
     *         completedByYou: bool,
     *         completedBy: string|null,
     *         instructions: string|null,
     *         resources: list<array{label: string, url: string|null}>,
     *     },
     *     lesson: array{title: string, teachingDate: string, passage: string|null, memoryVerse: string|null},
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'task' => [
                ...new ParentHomeworkItemResponse($this->item)->jsonSerialize(),
                'instructions' => $this->text->instructions,
                'resources' => $this->text->resources,
            ],
            'lesson' => [
                'title' => $this->lesson->title,
                'teachingDate' => $this->lesson->teachingDate,
                'passage' => $this->lesson->passage,
                'memoryVerse' => $this->lesson->memoryVerse,
            ],
        ];
    }
}
