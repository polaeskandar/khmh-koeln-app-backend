<?php

declare(strict_types=1);

namespace App\Context\Attendance\Domain\ValueObject;

use Doctrine\Common\Collections\ArrayCollection;

final readonly class SessionCounts
{
    private function __construct(
        public int $present,
        public ?int $rosterSize,
        public int $rosterPresent,
        public int $notMarked,
        public int $notOnRoster,
    ) {
    }

    /**
     * @param list<string> $presentIds
     */
    public static function open(array $presentIds): self
    {
        $present = new ArrayCollection($presentIds)->count();

        return new self($present, rosterSize: null, rosterPresent: 0, notMarked: 0, notOnRoster: $present);
    }

    /**
     * @param list<string> $rosterIds
     * @param list<string> $presentIds
     */
    public static function expected(array $rosterIds, array $presentIds): self
    {
        $present = new ArrayCollection($presentIds);
        $roster = new ArrayCollection($rosterIds);
        $rosterPresent = $present->filter($roster->contains(...))->count();

        return new self(
            present: $present->count(),
            rosterSize: $roster->count(),
            rosterPresent: $rosterPresent,
            notMarked: $roster->count() - $rosterPresent,
            notOnRoster: $present->count() - $rosterPresent,
        );
    }
}
