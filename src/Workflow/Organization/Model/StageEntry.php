<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Organization\Domain\Entity\Stage;

final readonly class StageEntry
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public int $position,
        public int $memberCount,
    ) {
    }

    public static function of(Stage $stage, int $memberCount): self
    {
        return new self(
            id: $stage->id()->toRfc4122(),
            name: $stage->name()->value,
            description: $stage->description()->value,
            position: $stage->position(),
            memberCount: $memberCount,
        );
    }
}
