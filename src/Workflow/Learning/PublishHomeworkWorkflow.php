<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\HomeworkWrites;
use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Learning\Model\ChangedHomework;
use App\Workflow\Learning\Model\LessonStages;

final readonly class PublishHomeworkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private HomeworkWrites $homework,
    ) {
    }

    public function __invoke(string $id): ChangedHomework
    {
        $this->access->require(Action::Update, Resource::Homework, refusal: 'homework.changeStageOnly');

        return ChangedHomework::of($this->homework->locked($id, $this->published(...)));
    }

    private function published(HomeworkTask $task): HomeworkTask
    {
        LessonStages::opened($this->structure->getStage($task->stageId()->toRfc4122()));

        return $this->homework->publish($task);
    }
}
