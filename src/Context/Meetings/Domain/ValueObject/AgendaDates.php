<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class AgendaDates
{
    /**
     * @param ReadableCollection<int, Schedule> $schedules
     * @param ReadableCollection<int, Session> $sessions
     * @param list<PlannedDate> $planned
     * @param ReadableCollection<string, list<string>> $present
     */
    public function __construct(
        public ReadableCollection $schedules,
        public ReadableCollection $sessions,
        public array $planned,
        public ReadableCollection $present = new ArrayCollection(),
    ) {
    }

    /**
     * @param ReadableCollection<string, list<string>> $present
     */
    public function withPresent(ReadableCollection $present): self
    {
        return new self($this->schedules, $this->sessions, $this->planned, $present);
    }

    /**
     * @return ReadableCollection<int, Session>
     */
    public function opened(): ReadableCollection
    {
        return $this->sessions->filter(static fn (Session $session): bool => $session->progress()->wasOpened());
    }
}
