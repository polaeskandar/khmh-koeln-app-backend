<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Application\Service\StageLessons;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonStatus;

final readonly class LessonLifecycleAbilities
{
    public function __construct(
        public bool $archive,
        public bool $restore,
        public bool $delete,
    ) {
    }

    public static function of(Lesson $lesson, bool $changesOpenStage, StageLessons $lessons): self
    {
        $archived = $lesson->state()->archivedAt !== null;
        $deletable = $changesOpenStage && $lesson->state()->status === LessonStatus::Draft;

        return new self(
            archive: $changesOpenStage && !$archived,
            restore: $changesOpenStage && $archived,
            delete: $deletable && !$lessons->hasPublishedHomework($lesson),
        );
    }
}
