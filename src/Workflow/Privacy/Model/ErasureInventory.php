<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ErasureInventory
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
        $counts = new ArrayCollection($outcome->counts());

        return new self(
            Collections::withoutKeys($counts, ErasureOutcome::KEPT)->toArray(),
            Collections::onlyKeys($counts, ErasureOutcome::KEPT)->toArray(),
            $mentions,
        );
    }
}
