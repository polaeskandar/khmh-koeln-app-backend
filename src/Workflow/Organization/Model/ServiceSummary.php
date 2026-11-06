<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use DateTimeImmutable;

final readonly class ServiceSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public int $tone,
        public ?DateTimeImmutable $archivedAt,
    ) {
    }
}
