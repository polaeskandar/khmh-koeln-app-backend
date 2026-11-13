<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LessonLifecycle;
use App\Context\Learning\Application\Service\LessonWrites;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonStatus;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Model\Ids;
use App\Workflow\Learning\Model\LessonStages;
use App\Workflow\Learning\Model\LessonStatusView;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class ChangeLessonStatusWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private LessonWrites $lessons,
        private LessonLifecycle $lifecycle,
    ) {
    }

    public function __invoke(string $id, string $status): LessonStatusView
    {
        $this->access->require(Action::Update, Resource::Lessons, refusal: 'lessons.changeStageOnly');

        $lesson = $this->lessons->editable(
            Ids::toUuid($id, notFound: 'lesson'),
            version: null,
            ensureAllowed: $this->ensureAllowed(...),
        );

        LessonStages::opened($this->structure->getStage($lesson->stageId()));

        $chosen = LessonStatus::tryFrom($status) ?? throw InvalidInput::field('status', 'validation.invalidChoice');

        return LessonStatusView::of($this->lifecycle->changeStatus($lesson, $chosen));
    }

    private function ensureAllowed(Lesson $lesson): void
    {
        $stage = $this->structure->getStage($lesson->stageId());
        $this->access->requireIn(Action::Update, Resource::Lessons, StructureGroup::ofStage($stage), missing: 'lesson');
    }
}
