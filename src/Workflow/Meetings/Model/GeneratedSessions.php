<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\ValueObject\GenerationRun;

final readonly class GeneratedSessions
{
    public function __construct(
        public int $created = 0,
        public int $existing = 0,
        public int $failed = 0,
        public bool $skipped = false,
    ) {
    }

    public static function of(GenerationRun $run): self
    {
        $result = $run->result;

        return new self($result->created, $result->existing, $result->failed, $run->skipped);
    }
}
