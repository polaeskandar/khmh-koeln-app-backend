<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class AgendaDates
{
    /**
     * @param ReadableCollection<int, Schedule> $schedules
     * @param ReadableCollection<int, Session> $sessions
     * @param list<PlannedDate> $planned
     */
    public function __construct(
        public ReadableCollection $schedules,
        public ReadableCollection $sessions,
        public array $planned,
    ) {
    }

    public function withoutCompleted(): self
    {
        $coming = $this->sessions->filter(
            static fn (Session $session): bool => $session->progress()->status !== SessionStatus::Completed,
        );

        return new self($this->schedules, $coming, $this->planned);
    }
}
