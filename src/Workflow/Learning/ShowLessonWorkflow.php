<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\StageLessons;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Learning\Model\LessonDetail;
use App\Workflow\Learning\Model\LessonDetailAbilities;
use App\Workflow\Learning\Model\LessonDetailView;
use App\Workflow\Learning\Model\LessonRows;
use App\Workflow\Learning\Model\LessonStageView;

final readonly class ShowLessonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StageLessons $lessons,
        private StructureLookup $structure,
        private MeetingLookup $meetings,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(string $id): LessonDetail
    {
        $this->access->require(Action::Read, Resource::Lessons);

        $reading = $this->lessons->reading($id);
        $lesson = $reading->lesson;
        $stage = $this->structure->stage($lesson->stageId()->toRfc4122());
        $can = LessonDetailAbilities::grantedBy($this->access, $lesson, $stage, $reading->today, $this->lessons);

        $row = LessonRows::of($this->meetings, $this->accounts, $this->lessons, [$lesson], $reading->homework)->rowOf(
            $lesson,
        );

        return new LessonDetail(
            stage: LessonStageView::of($stage),
            today: $reading->today->toString(),
            lesson: LessonDetailView::of($row, $reading, $can),
            can: $can,
        );
    }
}
