<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LessonArchives;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Learning\Model\LessonArchiveState;
use App\Workflow\Learning\Model\LessonStages;

final readonly class ArchiveLessonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private LessonArchives $archives,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $id): LessonArchiveState
    {
        $this->access->require(Action::Update, Resource::Lessons, refusal: 'lessons.changeStageOnly');

        $lesson = $this->archives->get($id);
        LessonStages::opened($this->structure->getStage($lesson->stageId()->toRfc4122()));

        return LessonArchiveState::of($this->archives->archive($lesson));
    }
}
