<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\MemberHomeworkDetail;
use DateTimeImmutable;
use JsonSerializable;
use Override;

final readonly class MemberHomeworkDetailResponse implements JsonSerializable
{
    public function __construct(
        private MemberHomeworkDetail $detail,
    ) {
    }

    /**
     * @return array{
     *     today: string,
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
        $detail = $this->detail;

        return [
            'today' => $detail->today,
            ...new HomeworkTaskResponse($detail->item, $detail->text, $detail->lesson)->jsonSerialize(),
        ];
    }
}
