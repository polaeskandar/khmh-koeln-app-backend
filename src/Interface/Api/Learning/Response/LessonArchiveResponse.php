<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\LessonArchiveState;
use DateTimeImmutable;

final readonly class LessonArchiveResponse
{
    public function __construct(
        public string $id,
        public ?DateTimeImmutable $archivedAt,
        public int $homeworkArchived,
    ) {
    }

    public static function of(LessonArchiveState $state): self
    {
        return new self($state->id, $state->archivedAt, $state->homeworkArchived);
    }
}
