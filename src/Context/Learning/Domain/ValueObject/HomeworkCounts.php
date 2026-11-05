<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

final readonly class HomeworkCounts
{
    public function __construct(
        public int $published = 0,
        public int $drafts = 0,
    ) {
    }

    public function total(): int
    {
        return $this->published + $this->drafts;
    }
}
