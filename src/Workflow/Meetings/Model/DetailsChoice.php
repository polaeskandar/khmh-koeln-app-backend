<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\ValueObject\DetailsDraft;

final readonly class DetailsChoice
{
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?string $location,
    ) {
    }

    public function draft(): DetailsDraft
    {
        return new DetailsDraft($this->name, $this->description, $this->location);
    }
}
