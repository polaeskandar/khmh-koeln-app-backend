<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\HomeworkWrites;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Problem\Model\Ids;
use App\Workflow\Learning\Model\HomeworkChoice;
use App\Workflow\Learning\Model\LessonStages;
use App\Workflow\Learning\Model\WrittenHomework;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class EditHomeworkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private HomeworkWrites $homework,
    ) {
    }

    public function __invoke(string $id, HomeworkChoice $choice, ?int $version): WrittenHomework
    {
        $this->access->require(Action::Update, Resource::Homework, refusal: 'homework.changeStageOnly');

        $edit = fn (HomeworkAssignment $task): HomeworkAssignment => $this->edited($task, $choice);

        return WrittenHomework::of($this->homework->whileEditable(
            Ids::toUuid($id, notFound: 'homework'),
            $version,
            $edit,
        ));
    }

    private function edited(HomeworkAssignment $task, HomeworkChoice $choice): HomeworkAssignment
    {
        $stage = $this->structure->getStage($task->stageId());

        $this->access->requireIn(
            Action::Update,
            Resource::Homework,
            StructureGroup::ofStage($stage),
            missing: 'homework',
        );

        LessonStages::opened($stage);

        return $this->homework->edit($task, $choice->draft());
    }
}
