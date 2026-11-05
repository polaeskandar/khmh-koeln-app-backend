<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Learning\Model\LessonDetail;
use App\Workflow\Learning\Model\LessonDetailView;
use App\Workflow\Learning\Model\LessonHomework;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageSummary;
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
     *     today: string,
     *     lesson: array<string, string|list<array{label: string, url: string|null}>|object|null>,
     *     homework: LessonHomework,
     *     can: array{
     *         manage: bool,
     *         markCompleted: bool,
     *         manageHomework: bool,
     *         viewNotes: bool,
     *         archive: bool,
     *         restore: bool,
     *         delete: bool,
     *     },
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $detail = $this->detail;
        $can = $detail->can;

        return [
            'service' => $detail->stage->service,
            'stage' => $detail->stage->stage,
            'today' => $detail->today,
            'lesson' => self::lessonOf($detail->lesson),
            'homework' => $detail->lesson->row->homework,
            'can' => [
                'manage' => $can->manage,
                'markCompleted' => $can->markCompleted,
                'manageHomework' => $can->manageHomework,
                'viewNotes' => $can->viewNotes,
                'archive' => $can->lifecycle->archive,
                'restore' => $can->lifecycle->restore,
                'delete' => $can->lifecycle->delete,
            ],
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
     *     meeting: MeetingViewResponse,
     *     scheduleId: string|null,
     *     summary: string|null,
     *     memoryVerse: string|null,
     *     objectives: string|null,
     *     teachingPoints: string|null,
     *     activities: string|null,
     *     materials: list<array{label: string, url: string|null}>,
     *     notes: string|null,
     *     completedAt: string|null,
     *     archivedAt: string|null,
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
            'completedAt' => $lesson->moments->completedAt,
            'archivedAt' => $lesson->moments->archivedAt,
        ];
    }
}
