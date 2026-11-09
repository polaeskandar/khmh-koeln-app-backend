<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Library\Time\Model\LocalDate;

final readonly class OffScheduleMeeting
{
    public function __construct(
        public string $date,
        public ?string $title,
    ) {
    }

    public static function of(?Schedule $schedule, LocalDate $date): self
    {
        return new self($date->toString(), $schedule?->details()->name);
    }
}
