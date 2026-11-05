<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\LessonStatusView;
use DateTimeImmutable;

final readonly class LessonStatusResponse
{
    public function __construct(
        public string $id,
        public string $status,
        public ?DateTimeImmutable $completedAt,
    ) {
    }

    public static function of(LessonStatusView $lesson): self
    {
        return new self($lesson->id, $lesson->status, $lesson->completedAt);
    }
}
