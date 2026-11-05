<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Request;

use App\Workflow\Learning\Model\HomeworkChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class HomeworkRequest
{
    /**
     * @param list<array<string, string|null>> $resources
     */
    public function __construct(
        public ?string $title = null,
        public ?string $instructions = null,
        public ?string $dueDate = null,
        #[Assert\All([
            new Assert\Collection(fields: [
                'label' => new Assert\Optional([new Assert\Type('string', message: 'validation.invalidType')]),
                'url' => new Assert\Optional([new Assert\Type('string', message: 'validation.invalidType')]),
            ], extraFieldsMessage: 'validation.unexpectedField'),
        ])]
        public array $resources = [],
    ) {
    }

    public function choice(): HomeworkChoice
    {
        return new HomeworkChoice($this->title, $this->instructions, $this->dueDate, $this->resources);
    }
}
