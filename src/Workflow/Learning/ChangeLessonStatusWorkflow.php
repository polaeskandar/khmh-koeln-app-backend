<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LessonStatuses;
use App\Context\Learning\Application\Service\LessonWrites;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Learning\Model\LessonStages;
use App\Workflow\Learning\Model\LessonStatusView;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class ChangeLessonStatusWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private LessonWrites $lessons,
        private LessonStatuses $statuses,
    ) {
    }

    public function __invoke(string $id, string $status): LessonStatusView
    {
        $this->access->require(Action::Update, Resource::Lessons, refusal: 'lessons.changeStageOnly');

        $lesson = $this->lessons->editable($id, version: null, ensureAllowed: $this->ensureAllowed(...));
        LessonStages::opened($this->structure->stage($lesson->stageId()->toRfc4122()));

        return LessonStatusView::of($this->statuses->change($lesson, $status));
    }

    private function ensureAllowed(Lesson $lesson): void
    {
        $stage = $this->structure->stage($lesson->stageId()->toRfc4122());
        $this->access->requireIn(Action::Update, Resource::Lessons, StructureGroup::ofStage($stage), missing: 'lesson');
    }
}
