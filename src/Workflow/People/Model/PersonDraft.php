<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

final readonly class PersonDraft
{
    public function __construct(
        public ?string $firstName,
        public ?string $lastName,
        public ?string $displayName,
        public ?PlacementChoice $placement,
        public bool $confirmDuplicate,
    ) {
    }
}
