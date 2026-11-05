<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\StageLessons;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Time\Model\LocalDate;

final readonly class LessonDetailAbilities
{
    public function __construct(
        public bool $manage,
        public bool $markCompleted,
        public bool $manageHomework,
        public bool $viewNotes,
        public LessonLifecycleAbilities $lifecycle,
    ) {
    }

    public static function grantedBy(
        AccessCheck $access,
        Lesson $lesson,
        Stage $stage,
        LocalDate $today,
        StageLessons $lessons,
    ): self {
        $updates = $access->allows(Action::Update, Resource::Lessons);
        $open = LessonStages::isOpenFor($lesson, $stage);
        $manage = $open && $updates;

        return new self(
            manage: $manage,
            markCompleted: $manage && $lesson->isTeachableOn($today),
            manageHomework: $open && $access->allows(Action::Update, Resource::Homework),
            viewNotes: $access->allows(Action::Read, Resource::Members),
            lifecycle: LessonLifecycleAbilities::of($lesson, LessonStages::isOpen($stage) && $updates, $lessons),
        );
    }
}
