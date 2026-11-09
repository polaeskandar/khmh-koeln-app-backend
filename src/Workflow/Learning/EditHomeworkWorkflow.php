<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\HomeworkWrites;
use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Learning\Model\HomeworkChoice;
use App\Workflow\Learning\Model\LessonStages;
use App\Workflow\Learning\Model\WrittenHomework;

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

        $edit = fn (HomeworkTask $task): HomeworkTask => $this->edited($task, $choice);

        return WrittenHomework::of($this->homework->whileEditable($id, $version, $edit));
    }

    private function edited(HomeworkTask $task, HomeworkChoice $choice): HomeworkTask
    {
        LessonStages::opened($this->structure->getStage($task->stageId()->toRfc4122()));

        return $this->homework->edit($task, $choice->draft());
    }
}
