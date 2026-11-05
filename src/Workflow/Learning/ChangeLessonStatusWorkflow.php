<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LessonStatuses;
use App\Context\Learning\Application\Service\LessonWrites;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Learning\Model\LessonStages;
use App\Workflow\Learning\Model\LessonStatusView;

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

        $lesson = $this->lessons->editable($id);
        LessonStages::opened($this->structure->stage($lesson->stageId()->toRfc4122()));

        return LessonStatusView::of($this->statuses->change($lesson, $status));
    }
}
