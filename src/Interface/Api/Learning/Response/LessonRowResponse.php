<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Learning\Model\LessonHomework;
use App\Workflow\Learning\Model\LessonRow;
use JsonSerializable;
use Override;

final readonly class LessonRowResponse implements JsonSerializable
{
    public function __construct(
        private LessonRow $row,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     passage: string|null,
     *     teachingDate: string,
     *     status: string,
     *     responsible: AccountReference|null,
     *     meeting: MeetingViewResponse,
     *     homework: LessonHomework,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $lesson = $this->row->lesson;

        return [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'passage' => $lesson->passage,
            'teachingDate' => $lesson->teachingDate,
            'status' => $lesson->status,
            'responsible' => $this->row->responsible,
            'meeting' => new MeetingViewResponse($this->row->meeting),
            'homework' => $this->row->homework,
        ];
    }
}
