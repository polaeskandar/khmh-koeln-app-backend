<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\Lesson;

final readonly class LessonPlanView
{
    /**
     * @param list<array{label: string, url: string|null}> $materials
     */
    public function __construct(
        public ?string $objectives,
        public ?string $teachingPoints,
        public ?string $activities,
        public array $materials,
        public ?string $notes,
    ) {
    }

    public static function of(Lesson $lesson): self
    {
        $plan = $lesson->plan();

        return new self(
            objectives: $plan->objectives,
            teachingPoints: $plan->teachingPoints,
            activities: $plan->activities,
            materials: $lesson->materials(),
            notes: $plan->notes,
        );
    }

    public function withoutNotes(): self
    {
        return new self($this->objectives, $this->teachingPoints, $this->activities, $this->materials, notes: null);
    }
}
