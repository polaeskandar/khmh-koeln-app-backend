<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Context\Learning\Domain\Enum\LessonStatus;
use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Learning\Model\LessonHomework;
use App\Workflow\Learning\Model\LessonRow;
use App\Workflow\Learning\Model\NoMeeting;
use App\Workflow\Learning\Model\OffScheduleMeeting;
use App\Workflow\Learning\Model\PlannedMeeting;
use App\Workflow\Learning\Model\SessionMeeting;
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
     *     status: LessonStatus,
     *     responsible: AccountReference|null,
     *     collaborators: list<AccountReference>,
     *     meeting: NoMeetingResponse|OffScheduleMeetingResponse|PlannedMeetingResponse|SessionMeetingResponse,
     *     homework: LessonHomework,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [...$this->fields(), 'homework' => $this->row->homework];
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     passage: string|null,
     *     teachingDate: string,
     *     status: LessonStatus,
     *     responsible: AccountReference|null,
     *     collaborators: list<AccountReference>,
     *     meeting: NoMeetingResponse|OffScheduleMeetingResponse|PlannedMeetingResponse|SessionMeetingResponse,
     * }
     */
    public function fields(): array
    {
        $lesson = $this->row->lesson;
        $meeting = $this->row->meeting;

        return [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'passage' => $lesson->passage,
            'teachingDate' => $lesson->teachingDate,
            'status' => $lesson->status,
            'responsible' => $this->row->responsible,
            'collaborators' => $this->row->collaborators,
            'meeting' => match (true) {
                $meeting instanceof NoMeeting => new NoMeetingResponse($meeting),
                $meeting instanceof OffScheduleMeeting => new OffScheduleMeetingResponse($meeting),
                $meeting instanceof PlannedMeeting => new PlannedMeetingResponse($meeting),
                $meeting instanceof SessionMeeting => new SessionMeetingResponse($meeting),
            },
        ];
    }
}
