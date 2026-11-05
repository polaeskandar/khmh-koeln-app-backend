<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Organization\Domain\Entity\Service;

final readonly class ServiceReference
{
    public function __construct(
        public string $id,
        public string $name,
        public int $tone,
        public bool $archived,
    ) {
    }

    public static function of(Service $service): self
    {
        return new self(
            id: $service->id()->toRfc4122(),
            name: $service->name()->value,
            tone: $service->tone()->value,
            archived: $service->isArchived(),
        );
    }
}
