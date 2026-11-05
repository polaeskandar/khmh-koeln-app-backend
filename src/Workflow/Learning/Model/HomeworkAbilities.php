<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Context\Organization\Domain\Entity\Stage;

final readonly class HomeworkAbilities
{
    public function __construct(
        public bool $edit,
        public bool $publish,
        public bool $viewProgress,
    ) {
    }

    public static function grantedBy(AccessCheck $access, HomeworkAssignment $task, Stage $stage): self
    {
        $open = $task->state()->archivedAt === null && $task->lesson()->state()->archivedAt === null;
        $edit = $open && LessonStages::isOpen($stage) && $access->allows(Action::Update, Resource::Homework);

        return new self(
            edit: $edit,
            publish: $edit && $task->state()->status === HomeworkStatus::Draft,
            viewProgress: $access->allows(Action::Read, Resource::Members),
        );
    }
}
