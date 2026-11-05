<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

final readonly class LessonNote
{
    public function __construct(
        public string $id,
        public string $text,
        public string $date,
        public string $stageId,
    ) {
    }
}
