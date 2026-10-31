<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class AccountStatus
{
    private function __construct(
        #[ORM\Column(name: 'approved_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $approvedAt,
        #[ORM\Column(name: 'disabled_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $disabledAt,
    ) {
    }

    public static function approvedAt(DateTimeImmutable $at): self
    {
        return new self(approvedAt: $at, disabledAt: null);
    }

    public function isActive(): bool
    {
        return $this->approvedAt !== null && $this->disabledAt === null;
    }
}
