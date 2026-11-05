<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\PlanDraft;

final readonly class LessonPlanChoice
{
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

    public function draft(): PlanDraft
    {
        return new PlanDraft(
            $this->objectives,
            $this->teachingPoints,
            $this->activities,
            $this->notes,
            $this->materials,
        );
    }
}
