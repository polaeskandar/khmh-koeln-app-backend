<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

final readonly class PlanDraft
{
    private const string MATERIALS_FIELD = 'plan.materials';

    /**
     * @param list<array<string, string|null>>|null $materials
     */
    public function __construct(
        public ?string $objectives,
        public ?string $teachingPoints,
        public ?string $activities,
        public ?string $notes,
        public ?array $materials,
    ) {
    }

    public function merged(LessonPlan $stored): LessonPlan
    {
        return new LessonPlan(
            DraftText::merged($this->objectives, $stored->objectives),
            DraftText::merged($this->teachingPoints, $stored->teachingPoints),
            DraftText::merged($this->activities, $stored->activities),
            DraftText::merged($this->notes, $stored->notes),
        );
    }

    /**
     * @param list<array{label: string, url: string|null}> $stored
     */
    public function materials(array $stored): Materials
    {
        return Materials::of($this->materials ?? $stored, self::MATERIALS_FIELD);
    }
}
