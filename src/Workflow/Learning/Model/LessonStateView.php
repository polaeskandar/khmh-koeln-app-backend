<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\Lesson;
use DateTimeImmutable;

final readonly class LessonStateView
{
    public function __construct(
        public ?DateTimeImmutable $completedAt,
        public ?DateTimeImmutable $archivedAt,
        public int $version,
    ) {
    }

    public static function of(Lesson $lesson): self
    {
        $state = $lesson->state();

        return new self($state->completedAt, $state->archivedAt, $lesson->version());
    }
}
