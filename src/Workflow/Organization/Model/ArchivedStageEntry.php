<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Organization\Domain\Entity\Stage;
use DateTimeInterface;

final readonly class ArchivedStageEntry
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $archivedAt,
    ) {
    }

    public static function of(Stage $stage): self
    {
        return new self(
            id: $stage->id()->toRfc4122(),
            name: $stage->name()->value,
            archivedAt: $stage->archivedAt()?->format(DateTimeInterface::ATOM),
        );
    }
}
