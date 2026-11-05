<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\ValueObject;

final readonly class DescriptionChange
{
    private function __construct(
        private bool $replaces,
        private ?string $description,
    ) {
    }

    public static function keep(): self
    {
        return new self(replaces: false, description: null);
    }

    public static function to(?string $description): self
    {
        return new self(replaces: true, description: $description);
    }

    public function appliedTo(?string $current): ?string
    {
        return $this->replaces ? $this->description : $current;
    }
}
