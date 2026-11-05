<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

final readonly class ErasedElsewhere
{
    public function __construct(
        public int $sessions,
        public int $marks,
        public int $reports,
    ) {
    }
}
