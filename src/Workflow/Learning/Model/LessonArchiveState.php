<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\ArchivedLesson;
use DateTimeImmutable;

final readonly class LessonArchiveState
{
    public function __construct(
        public string $id,
        public ?DateTimeImmutable $archivedAt,
        public int $homeworkArchived,
    ) {
    }

    public static function of(ArchivedLesson $archived): self
    {
        return new self(
            id: $archived->lesson->id()->toRfc4122(),
            archivedAt: $archived->lesson->state()->archivedAt,
            homeworkArchived: $archived->homeworkArchived,
        );
    }
}
