<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\ValueObject\Materials;

final readonly class HomeworkTextView
{
    /**
     * @param list<array{label: string, url: string|null}> $resources
     */
    public function __construct(
        public string $title,
        public ?string $instructions,
        public ?string $dueDate,
        public array $resources,
    ) {
    }

    public static function of(HomeworkTask $task): self
    {
        $text = $task->text();

        return new self(
            $text->title,
            $text->instructions,
            $text->dueDate?->toString(),
            Materials::of($task->resources(), 'resources')->toArray(),
        );
    }
}
