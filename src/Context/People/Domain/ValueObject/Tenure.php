<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Model\LocalDate;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Tenure
{
    private function __construct(
        #[ORM\Column(name: 'starts_on', type: 'local_date')]
        public LocalDate $startsOn,
        #[ORM\Column(name: 'ends_on', type: 'local_date', nullable: true)]
        public ?LocalDate $endsOn,
    ) {
    }

    public static function from(LocalDate $startsOn): self
    {
        return new self($startsOn, endsOn: null);
    }

    public function includes(LocalDate $date): bool
    {
        return !$date->isBefore($this->startsOn) && $this->endsOn?->isBefore($date) !== true;
    }

    public function endedFrom(LocalDate $today): ?self
    {
        if ($this->endsOn !== null) {
            throw new RuleViolation('people.membershipEnded');
        }

        return $this->startsOn->isBefore($today) ? new self($this->startsOn, $today->addDays(-1)) : null;
    }
}
