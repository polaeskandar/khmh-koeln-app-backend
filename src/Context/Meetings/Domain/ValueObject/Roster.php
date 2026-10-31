<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Enum\RosterMode;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Embeddable]
final readonly class Roster
{
    public function __construct(
        #[ORM\Column(name: 'service_id', type: UuidType::NAME)]
        public Uuid $serviceId,
        #[ORM\Column(name: 'stage_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $stageId,
        #[ORM\Column(name: 'roster_mode', length: 8, enumType: RosterMode::class)]
        public RosterMode $mode,
    ) {
    }
}
