<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Organization\Domain\ValueObject\DescriptionChange;

final readonly class DetailChanges
{
    public function __construct(
        public ?string $name,
        public ?string $description,
        public bool $describes,
    ) {
    }

    public function descriptionChange(): DescriptionChange
    {
        return $this->describes ? DescriptionChange::to($this->description) : DescriptionChange::keep();
    }
}
