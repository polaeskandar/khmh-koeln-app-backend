<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\HomeworkDraft;

final readonly class HomeworkChoice
{
    /**
     * @param list<array<string, string|null>>|null $resources
     */
    public function __construct(
        public ?string $title,
        public ?string $instructions,
        public ?string $dueDate,
        public ?array $resources,
    ) {
    }

    public function draft(): HomeworkDraft
    {
        return new HomeworkDraft($this->title, $this->instructions, $this->dueDate, $this->resources);
    }
}
