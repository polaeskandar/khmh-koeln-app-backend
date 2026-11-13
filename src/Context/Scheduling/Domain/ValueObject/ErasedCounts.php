<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class ErasedCounts
{
    public function __construct(
        #[ORM\Column(name: 'erased_roster', type: Types::INTEGER, options: ['default' => 0])]
        public int $roster = 0,
        #[ORM\Column(name: 'erased_present', type: Types::INTEGER, options: ['default' => 0])]
        public int $present = 0,
        #[ORM\Column(name: 'erased_roster_present', type: Types::INTEGER, options: ['default' => 0])]
        public int $rosterPresent = 0,
    ) {
    }
}
