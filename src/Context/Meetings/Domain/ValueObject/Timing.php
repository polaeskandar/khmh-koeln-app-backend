<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Timing
{
    public function __construct(
        #[ORM\Column(name: 'starts_at', type: Types::DATETIMETZ_IMMUTABLE)]
        public DateTimeImmutable $startsAt,
        #[ORM\Column(name: 'ends_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $endsAt,
    ) {
    }
}
