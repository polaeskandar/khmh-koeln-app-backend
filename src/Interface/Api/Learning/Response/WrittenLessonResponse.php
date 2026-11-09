<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Context\Learning\Domain\Enum\LessonStatus;
use App\Workflow\Learning\Model\WrittenLesson;

final readonly class WrittenLessonResponse
{
    public function __construct(
        public string $id,
        public LessonStatus $status,
        public int $version,
    ) {
    }

    public static function of(WrittenLesson $lesson): self
    {
        return new self($lesson->id, $lesson->status, $lesson->version);
    }
}
