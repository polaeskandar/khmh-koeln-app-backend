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
    public static function open(array $presentIds, ErasedAttendance $erased): self
    {
        $present = new ArrayCollection($presentIds)->count() + $erased->present;

        return new self($present, rosterSize: null, rosterPresent: 0, notMarked: 0, notOnRoster: $present);
    }

    /**
     * @param list<string> $rosterIds
     * @param list<string> $presentIds
     */
    public static function expected(array $rosterIds, array $presentIds, ErasedAttendance $erased): self
    {
        $marked = new ArrayCollection($presentIds);
        $roster = new ArrayCollection($rosterIds);
        $present = $marked->count() + $erased->present;
        $rosterSize = $roster->count() + $erased->roster;
        $rosterPresent = $marked->filter($roster->contains(...))->count() + $erased->rosterPresent;

        return new self(
            present: $present,
            rosterSize: $rosterSize,
            rosterPresent: $rosterPresent,
            notMarked: $rosterSize - $rosterPresent,
            notOnRoster: $present - $rosterPresent,
        );
    }
}
