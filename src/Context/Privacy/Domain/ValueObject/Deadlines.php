<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\ValueObject;

use App\Library\Time\Model\LocalDate;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Deadlines
{
    public function __construct(
        #[ORM\Column(name: 'due_on', type: 'local_date')]
        public LocalDate $dueOn,
        #[ORM\Column(name: 'erases_on', type: 'local_date', nullable: true)]
        public ?LocalDate $erasesOn = null,
    ) {
    }
}
