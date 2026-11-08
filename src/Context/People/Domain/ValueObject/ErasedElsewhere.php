<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use Doctrine\Common\Collections\ArrayCollection;

final readonly class ErasedElsewhere
{
    public function __construct(
        public int $sessions,
        public int $marks,
        public int $reports,
    ) {
    }

    /**
     * @param list<array<string, int>> $erased
     */
    public static function of(array $erased): self
    {
        $total = static fn (string $kind): int => new ArrayCollection($erased)->reduce(
            static fn (int $sum, array $counts): int => $sum + ($counts[$kind] ?? 0),
            0,
        );

        return new self($total('sessions'), $total('marks'), $total('reports'));
    }
}
