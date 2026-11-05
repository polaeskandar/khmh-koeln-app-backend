<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

final readonly class ErasureReview
{
    /**
     * @param array<string, int> $erase
     * @param array<string, int> $keep
     * @param list<Mention> $mentions
     */
    public function __construct(
        public array $erase,
        public array $keep,
        public array $mentions,
    ) {
    }

    /**
     * @param list<Mention> $mentions
     */
    public static function of(ErasureOutcome $outcome, array $mentions): self
    {
        return new self($outcome->erased(), $outcome->kept(), $mentions);
    }
}
