<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Library\Time\Model\LocalTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class TimeSlot
{
    public function __construct(
        #[ORM\Column(name: 'start_time', type: 'local_time')]
        public LocalTime $startTime,
        #[ORM\Column(name: 'end_time', type: 'local_time', nullable: true)]
        public ?LocalTime $endTime,
    ) {
    }
}
