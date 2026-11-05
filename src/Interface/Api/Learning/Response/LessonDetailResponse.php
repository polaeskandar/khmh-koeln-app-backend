<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Learning\Model\LessonDetail;
use App\Workflow\Learning\Model\LessonDetailView;
use App\Workflow\Learning\Model\LessonHomework;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageSummary;
use DateTimeImmutable;
use JsonSerializable;
use Override;

final readonly class LessonDetailResponse implements JsonSerializable
{
    public function __construct(
        private LessonDetail $detail,
    ) {
    }

    /**
     * @return array{
     *     service: ServiceReference,
     *     stage: StageSummary,
     *     archived: bool,
     *     today: string,
     *     lesson: array<string, string|int|list<array{label: string, url: string|null}|AccountReference>|object|null>,
     *     homework: LessonHomework,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $detail = $this->detail;

        return [
            'service' => $detail->stage->service,
            'stage' => $detail->stage->stage,
            'archived' => $detail->stage->archived,
            'today' => $detail->today,
            'lesson' => self::lessonOf($detail->lesson),
            'homework' => $detail->lesson->row->homework,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     passage: string|null,
     *     teachingDate: string,
     *     status: string,
     *     responsible: AccountReference|null,
     *     collaborators: list<AccountReference>,
     *     meeting: MeetingViewResponse,
     *     scheduleId: string|null,
     *     summary: string|null,
     *     memoryVerse: string|null,
     *     objectives: string|null,
     *     teachingPoints: string|null,
     *     activities: string|null,
     *     materials: list<array{label: string, url: string|null}>,
     *     notes: string|null,
     *     completedAt: DateTimeImmutable|null,
     *     archivedAt: DateTimeImmutable|null,
     *     version: int,
     * }
     */
    private static function lessonOf(LessonDetailView $lesson): array
    {
        $plan = $lesson->plan;

        return [
            ...new LessonRowResponse($lesson->row)->fields(),
            'scheduleId' => $lesson->scheduleId,
            'summary' => $lesson->text->summary,
            'memoryVerse' => $lesson->text->memoryVerse,
            'objectives' => $plan->objectives,
            'teachingPoints' => $plan->teachingPoints,
            'activities' => $plan->activities,
            'materials' => $plan->materials,
            'notes' => $plan->notes,
            'completedAt' => $lesson->state->completedAt,
            'archivedAt' => $lesson->state->archivedAt,
            'version' => $lesson->state->version,
        ];
    }
}
