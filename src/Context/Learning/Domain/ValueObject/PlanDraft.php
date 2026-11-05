<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

final readonly class PlanDraft
{
    private const string MATERIALS_FIELD = 'plan.materials';

    /**
     * @param list<array<string, string|null>> $materials
     */
    public function __construct(
        public ?string $objectives,
        public ?string $teachingPoints,
        public ?string $activities,
        public ?string $notes,
        public array $materials,
    ) {
    }

    public function plan(): LessonPlan
    {
        return new LessonPlan(
            DraftText::of($this->objectives),
            DraftText::of($this->teachingPoints),
            DraftText::of($this->activities),
            DraftText::of($this->notes),
        );
    }

    public function materials(): Materials
    {
        return Materials::of($this->materials, self::MATERIALS_FIELD);
    }
}
