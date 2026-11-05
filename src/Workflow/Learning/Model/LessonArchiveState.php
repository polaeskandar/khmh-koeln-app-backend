<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\ArchivedLesson;
use App\Library\Time\Client\Calendar;

final readonly class LessonArchiveState
{
    public function __construct(
        public string $id,
        public ?string $archivedAt,
        public int $homeworkArchived,
        public int $version,
    ) {
    }

    public static function of(ArchivedLesson $archived, Calendar $calendar): self
    {
        $at = $archived->lesson->state()->archivedAt;

        return new self(
            id: $archived->lesson->id()->toRfc4122(),
            archivedAt: $at === null ? null : $calendar->formatInstant($at),
            homeworkArchived: $archived->homeworkArchived,
            version: $archived->lesson->version(),
        );
    }
}
