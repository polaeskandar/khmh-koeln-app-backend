<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use Doctrine\Common\Collections\ArrayCollection;

final readonly class AttendanceOverview
{
    /**
     * @param list<OverviewCard> $sessions
     * @param list<OverviewCard> $earlier
     * @param list<OverviewCard> $closed
     */
    public function __construct(
        public string $today,
        public array $sessions,
        public array $earlier,
        public array $closed,
        public int $openCount,
    ) {
    }

    /**
     * @param list<OverviewCard> $sessions
     * @param list<OverviewCard> $earlier
     * @param list<OverviewCard> $closed
     */
    public static function of(string $today, array $sessions, array $earlier, array $closed): self
    {
        $open = new ArrayCollection([
            ...$sessions,
            ...$earlier,
        ])->filter(static fn (OverviewCard $card): bool => $card->active);

        return new self($today, $sessions, $earlier, $closed, $open->count());
    }
}
