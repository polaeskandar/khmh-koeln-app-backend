<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Session;

final readonly class SessionDetails
{
    public function __construct(
        public string $id,
        public ?string $scheduleId,
        public string $occurrenceDate,
        public string $title,
        public ?string $location,
    ) {
    }

    public static function of(Session $session): self
    {
        $schedule = $session->schedule();
        $details = $session->details();

        return new self(
            id: $session->id()->toRfc4122(),
            scheduleId: $schedule->isOneOff() ? null : $schedule->id()->toRfc4122(),
            occurrenceDate: $session->occurrenceDate()->toString(),
            title: $details->name,
            location: $details->location,
        );
    }

    public function isOneOff(): bool
    {
        return $this->scheduleId === null;
    }
}
