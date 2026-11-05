<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Session;

final readonly class SessionDetails
{
    public function __construct(
        public string $id,
        public string $scheduleId,
        public string $occurrenceDate,
        public string $title,
        public ?string $location,
    ) {
    }

    public static function of(Session $session): self
    {
        $schedule = $session->schedule();

        return new self(
            id: $session->id()->toRfc4122(),
            scheduleId: $schedule->id()->toRfc4122(),
            occurrenceDate: $session->occurrenceDate()->toString(),
            title: $schedule->details()->name,
            location: $schedule->details()->location,
        );
    }
}
