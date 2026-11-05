<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Library\Time\Model\LocalDate;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class DateSpan
{
    public function __construct(
        #[ORM\Column(name: 'starts_on', type: 'local_date')]
        public LocalDate $startsOn,
        #[ORM\Column(name: 'ends_on', type: 'local_date', nullable: true)]
        public ?LocalDate $endsOn,
    ) {
    }

    public function includes(LocalDate $date): bool
    {
        $started = !$date->isBefore($this->startsOn);

        return $started && ($this->endsOn === null || !$this->endsOn->isBefore($date));
    }
}
