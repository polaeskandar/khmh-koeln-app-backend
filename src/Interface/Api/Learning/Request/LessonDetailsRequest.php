<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Request;

use App\Workflow\Learning\Model\LessonDetailsChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class LessonDetailsRequest
{
    /**
     * @param list<string>|null $collaboratorIds
     */
    public function __construct(
        #[Assert\NotBlank(message: 'lessons.titleRequired', allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 160, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $title = null,
        #[Assert\Length(max: 1000, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $summary = null,
        #[Assert\Length(max: 200, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $passage = null,
        #[Assert\Length(max: 500, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $memoryVerse = null,
        #[Assert\All([new Assert\Uuid(message: 'validation.invalidType')])]
        public ?array $collaboratorIds = null,
    ) {
    }

    public function choice(): LessonDetailsChoice
    {
        return new LessonDetailsChoice(
            $this->title,
            $this->summary,
            $this->passage,
            $this->memoryVerse,
            $this->collaboratorIds ?? [],
        );
    }
}
