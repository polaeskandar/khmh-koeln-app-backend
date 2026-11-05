<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

final readonly class TaughtLesson
{
    public function __construct(
        public string $title,
        public string $teachingDate,
        public ?string $passage,
        public ?string $memoryVerse,
    ) {
    }
}
