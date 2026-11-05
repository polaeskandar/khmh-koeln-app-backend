<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\TextDraft;

final readonly class LessonDetailsChoice
{
    /**
     * @param list<string> $collaboratorIds
     */
    public function __construct(
        public ?string $title,
        public ?string $summary,
        public ?string $passage,
        public ?string $memoryVerse,
        public array $collaboratorIds,
    ) {
    }

    public function draft(): TextDraft
    {
        return new TextDraft($this->title, $this->summary, $this->passage, $this->memoryVerse);
    }
}
