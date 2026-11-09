<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\ValueObject;

final readonly class ErasureRun
{
    public function __construct(
        public int $erased = 0,
        public int $failed = 0,
    ) {
    }

    public function plus(self $other): self
    {
        return new self($this->erased + $other->erased, $this->failed + $other->failed);
    }
}
