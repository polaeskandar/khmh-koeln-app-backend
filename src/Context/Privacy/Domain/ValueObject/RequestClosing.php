<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\ValueObject;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Embeddable]
final readonly class RequestClosing
{
    public function __construct(
        #[ORM\Column(name: 'closed_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $at = null,
        #[ORM\Column(name: 'closed_by', type: UuidType::NAME, nullable: true)]
        public ?Uuid $by = null,
        #[ORM\Column(name: 'decline_reason', type: Types::TEXT, nullable: true)]
        public ?string $declineReason = null,
    ) {
    }
}
