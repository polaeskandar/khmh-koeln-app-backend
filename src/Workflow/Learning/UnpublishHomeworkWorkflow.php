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
use App\Library\Time\Client\Calendar;
use App\Workflow\Learning\Model\ChangedHomework;
use App\Workflow\Learning\Model\LessonStages;

final readonly class UnpublishHomeworkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private HomeworkWrites $homework,
        private HomeworkLifecycle $lifecycle,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $id): ChangedHomework
    {
        $this->access->require(Action::Update, Resource::Homework, refusal: 'homework.changeStageOnly');

        return ChangedHomework::of($this->homework->locked($id, $this->changed(...)), $this->calendar);
    }

    private function changed(HomeworkAssignment $task): HomeworkAssignment
    {
        LessonStages::opened($this->structure->stage($task->stageId()->toRfc4122()));

        return $this->lifecycle->unpublish($task);
    }
}
