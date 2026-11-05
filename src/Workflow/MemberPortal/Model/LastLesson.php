<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

use App\Context\Learning\Domain\ValueObject\TaughtLesson;

final readonly class LastLesson
{
    public function __construct(
        public string $title,
        public string $teachingDate,
        public ?string $passage,
        public ?string $memoryVerse,
    ) {
    }

    public static function of(?TaughtLesson $lesson): ?self
    {
        if ($lesson === null) {
            return null;
        }

        return new self($lesson->title, $lesson->teachingDate, $lesson->passage, $lesson->memoryVerse);
    }
}
