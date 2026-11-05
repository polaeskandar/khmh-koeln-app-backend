<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LessonArchives;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Learning\Model\LessonStages;

final readonly class RestoreLessonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private LessonArchives $archives,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $id): string
    {
        $this->access->require(Action::Update, Resource::Lessons, refusal: 'lessons.changeStageOnly');

        $lesson = $this->archives->find($id);
        LessonStages::opened($this->structure->stage($lesson->stageId()->toRfc4122()));

        return $this->archives->restore($lesson)->id()->toRfc4122();
    }
}
