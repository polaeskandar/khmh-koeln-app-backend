<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Time\Model\LocalDate;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Embeddable]
final readonly class MeetingLink
{
    public function __construct(
        #[ORM\Column(name: 'schedule_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $scheduleId,
        #[ORM\Column(name: 'teaching_date', type: 'local_date')]
        public LocalDate $teachingDate,
    ) {
    }
}
