<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LessonArchives;
use App\Context\Learning\Application\Service\LessonDeletions;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Learning\Model\LessonStages;

final readonly class DeleteLessonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private LessonArchives $archives,
        private LessonDeletions $deletions,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Update, Resource::Lessons, refusal: 'lessons.changeStageOnly');

        $lesson = $this->archives->get($id);
        LessonStages::opened($this->structure->getStage($lesson->stageId()->toRfc4122()));

        $this->deletions->delete($lesson);
    }
}
