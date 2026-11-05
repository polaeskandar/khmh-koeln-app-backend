<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

final readonly class GenerationRun
{
    public function __construct(
        public bool $skipped,
        public GenerationResult $result,
    ) {
    }
}
