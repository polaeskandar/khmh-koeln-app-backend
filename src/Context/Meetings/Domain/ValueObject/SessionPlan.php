<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Enum\RosterMode;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class SessionPlan
{
    public function __construct(
        #[ORM\Column(name: 'title', length: 120)]
        public string $title,
        #[ORM\Column(name: 'location', length: 120, nullable: true)]
        public ?string $location,
        #[ORM\Embedded(TimeSlot::class, columnPrefix: false)]
        public TimeSlot $timeSlot,
        #[ORM\Column(name: 'roster_mode', length: 8, enumType: RosterMode::class)]
        public RosterMode $rosterMode,
    ) {
    }

    public static function of(Schedule $schedule): self
    {
        $details = $schedule->details();

        return new self($details->name, $details->location, $schedule->timeSlot(), $schedule->roster()->mode);
    }
}
