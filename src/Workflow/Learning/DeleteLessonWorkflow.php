<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LessonLifecycle;
use App\Context\Learning\Application\Service\LessonRemovals;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Problem\Model\Ids;
use App\Workflow\Learning\Model\LessonStages;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class DeleteLessonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private LessonLifecycle $lifecycle,
        private LessonRemovals $removals,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Update, Resource::Lessons, refusal: 'lessons.changeStageOnly');

        $lesson = $this->lifecycle->get(Ids::toUuid($id, notFound: 'lesson'));
        $stage = $this->structure->stage($lesson->stageId());
        $this->access->requireIn(Action::Update, Resource::Lessons, StructureGroup::ofStage($stage), missing: 'lesson');
        LessonStages::opened($stage);

        $this->removals->delete($lesson);
    }
}
