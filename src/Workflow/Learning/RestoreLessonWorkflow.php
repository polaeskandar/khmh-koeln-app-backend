<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LessonArchives;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Problem\Model\Ids;
use App\Workflow\Learning\Model\LessonStages;
use App\Workflow\Learning\Model\WrittenLesson;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class RestoreLessonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private LessonArchives $archives,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $id): WrittenLesson
    {
        $this->access->require(Action::Update, Resource::Lessons, refusal: 'lessons.changeStageOnly');

        $lesson = $this->archives->get(Ids::toUuid($id, notFound: 'lesson'));
        $stage = $this->structure->stage($lesson->stageId());
        $this->access->requireIn(Action::Update, Resource::Lessons, StructureGroup::ofStage($stage), missing: 'lesson');
        LessonStages::opened($stage);

        return WrittenLesson::of($this->archives->restore($lesson));
    }
}
