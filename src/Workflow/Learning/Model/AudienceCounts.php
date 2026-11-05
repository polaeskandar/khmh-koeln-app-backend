<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\People\Domain\Entity\Person;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class AudienceCounts
{
    public function __construct(
        public int $assigned,
        public int $completed,
        public int $completedAfterDueDate,
        public AudienceReach $open,
        public int $reportedTotal,
    ) {
    }

    public static function of(TaskAudience $audience, AudienceAccounts $accounts): self
    {
        $members = new ArrayCollection($audience->members);
        $reported = $members->filter(static fn (Person $person): bool => $audience->reportOf($person) !== null);
        $open = $members->filter(static fn (Person $person): bool => $audience->reportOf($person) === null);

        return new self(
            assigned: $members->count(),
            completed: $reported->count(),
            completedAfterDueDate: $reported
                ->filter(static fn (Person $person): bool => $audience->reportOf($person)?->late === true)
                ->count(),
            open: AudienceReach::of($open, $accounts),
            reportedTotal: $audience->reports->count(),
        );
    }

    public function notCompleted(): int
    {
        return $this->assigned - $this->completed;
    }
}
