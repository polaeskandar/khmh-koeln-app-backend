<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\HomeworkLifecycle;
use App\Context\Learning\Application\Service\HomeworkWrites;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Problem\Model\Ids;
use App\Workflow\Learning\Model\ChangedHomework;
use App\Workflow\Learning\Model\LessonStages;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class ArchiveHomeworkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private HomeworkWrites $homework,
        private HomeworkLifecycle $lifecycle,
    ) {
    }

    public function __invoke(string $id): ChangedHomework
    {
        $this->access->require(Action::Update, Resource::Homework, refusal: 'homework.changeStageOnly');

        return ChangedHomework::of($this->homework->whileTaskLocked(
            Ids::toUuid($id, notFound: 'homework'),
            $this->changed(...),
        ));
    }

    private function changed(HomeworkAssignment $task): HomeworkAssignment
    {
        $stage = $this->structure->stage($task->stageId());

        $this->access->requireIn(
            Action::Update,
            Resource::Homework,
            StructureGroup::ofStage($stage),
            missing: 'homework',
        );

        LessonStages::opened($stage);

        return $this->lifecycle->archive($task);
    }
}
