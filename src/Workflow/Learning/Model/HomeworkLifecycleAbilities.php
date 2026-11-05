<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\ValueObject\TaskReports;
use App\Context\Organization\Domain\Entity\Stage;

final readonly class HomeworkLifecycleAbilities
{
    public function __construct(
        public bool $unpublish,
        public bool $archive,
        public bool $restore,
        public bool $delete,
    ) {
    }

    public static function grantedBy(
        AccessCheck $access,
        HomeworkAssignment $task,
        Stage $stage,
        TaskReports $reports,
    ): self {
        if (!LessonStages::isOpen($stage) || !$access->allows(Action::Update, Resource::Homework)) {
            return new self(unpublish: false, archive: false, restore: false, delete: false);
        }

        $state = $task->state();
        $current = $state->archivedAt === null;

        return new self(
            unpublish: $task->isVisibleToParents() && $reports->of($task)->isEmpty(),
            archive: $current,
            restore: !$current && $task->lesson()->state()->archivedAt === null,
            delete: $state->publishedAt === null,
        );
    }
}
