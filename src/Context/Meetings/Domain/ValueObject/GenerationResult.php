<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

final readonly class GenerationResult
{
    public function __construct(
        public int $created = 0,
        public int $existing = 0,
        public int $failed = 0,
    ) {
    }

    public function plus(self $other): self
    {
        return new self(
            $this->created + $other->created,
            $this->existing + $other->existing,
            $this->failed + $other->failed,
        );
    }
}
