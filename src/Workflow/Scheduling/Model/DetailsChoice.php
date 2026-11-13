<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\ValueObject\DetailsDraft;

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
