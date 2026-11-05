<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\LessonReading;

final readonly class LessonMoments
{
    public function __construct(
        public ?string $completedAt,
        public ?string $archivedAt,
        public int $version,
    ) {
    }

    public static function of(LessonReading $reading): self
    {
        return new self($reading->completedAt, $reading->archivedAt, $reading->lesson->version());
    }
}
