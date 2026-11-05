<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Organization\Domain\Entity\Stage;

final readonly class StageSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public int $position,
        public bool $archived,
    ) {
    }

    public static function of(Stage $stage): self
    {
        return new self(
            id: $stage->id()->toRfc4122(),
            name: $stage->name()->value,
            position: $stage->position(),
            archived: $stage->isArchived(),
        );
    }
}
