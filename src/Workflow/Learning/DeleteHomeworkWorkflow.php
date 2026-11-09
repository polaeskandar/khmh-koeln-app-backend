<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\HomeworkLifecycle;
use App\Context\Learning\Application\Service\HomeworkWrites;
use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Learning\Model\LessonStages;

final readonly class DeleteHomeworkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private HomeworkWrites $homework,
        private HomeworkLifecycle $lifecycle,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Update, Resource::Homework, refusal: 'homework.changeStageOnly');

        $this->homework->locked($id, $this->deleted(...));
    }

    private function deleted(HomeworkTask $task): HomeworkTask
    {
        LessonStages::opened($this->structure->stage($task->stageId()->toRfc4122()));

        return $this->lifecycle->delete($task);
    }
}
