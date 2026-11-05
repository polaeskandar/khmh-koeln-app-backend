<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\ValueObject\GenerationResult;

final readonly class GeneratedSessions
{
    public function __construct(
        public int $created = 0,
        public int $existing = 0,
        public int $failed = 0,
    ) {
    }

    public static function of(GenerationResult $result): self
    {
        return new self($result->created, $result->existing, $result->failed);
    }
}
