<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\Entity\Schedule;

final readonly class ScheduleDetails
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ?string $location,
    ) {
    }

    public static function of(Schedule $schedule): self
    {
        $details = $schedule->details();

        return new self(
            id: $schedule->id()->toRfc4122(),
            name: $details->name,
            description: $details->description,
            location: $details->location,
        );
    }
}
