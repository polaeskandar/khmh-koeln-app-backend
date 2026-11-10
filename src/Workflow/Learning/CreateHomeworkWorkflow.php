<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\HomeworkWrites;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Problem\Model\Ids;
use App\Workflow\Learning\Model\HomeworkChoice;
use App\Workflow\Learning\Model\LessonStages;
use App\Workflow\Learning\Model\WrittenHomework;
use App\Workflow\Organization\Model\StructureGroup;
use Symfony\Component\Uid\Uuid;

final readonly class CreateHomeworkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private HomeworkWrites $homework,
    ) {
    }

    public function __invoke(string $lessonId, string $accountId, HomeworkChoice $choice): WrittenHomework
    {
        $this->access->require(Action::Create, Resource::Homework, refusal: 'homework.assignStageOnly');

        $lesson = $this->homework->lessonToAssign(Ids::toUuid($lessonId, notFound: 'lesson'));
        $stage = $this->structure->stage($lesson->stageId());

        $this->access->requireIn(
            Action::Create,
            Resource::Homework,
            StructureGroup::ofStage($stage),
            missing: 'lesson',
        );

        LessonStages::opened($stage);

        return WrittenHomework::of($this->homework->create($lesson, $choice->draft(), Uuid::fromString($accountId)));
    }
}
