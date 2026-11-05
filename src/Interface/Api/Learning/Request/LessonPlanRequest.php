<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Request;

use App\Workflow\Learning\Model\LessonPlanChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class LessonPlanRequest
{
    /**
     * @param list<array<string, string|null>>|null $materials
     */
    public function __construct(
        #[Assert\Length(max: 4000, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $objectives = null,
        #[Assert\Length(max: 4000, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $teachingPoints = null,
        #[Assert\Length(max: 4000, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $activities = null,
        #[Assert\Length(max: 4000, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $notes = null,
        #[Assert\All([
            new Assert\Collection(fields: [
                'label' => new Assert\Optional([new Assert\Type('string', message: 'validation.invalidType')]),
                'url' => new Assert\Optional([new Assert\Type('string', message: 'validation.invalidType')]),
            ], extraFieldsMessage: 'validation.unexpectedField'),
        ])]
        public ?array $materials = null,
    ) {
    }

    public function choice(): LessonPlanChoice
    {
        return new LessonPlanChoice(
            $this->objectives,
            $this->teachingPoints,
            $this->activities,
            $this->notes,
            $this->materials,
        );
    }
}
