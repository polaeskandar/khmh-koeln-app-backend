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

    public static function pending(): self
    {
        return new self(approvedAt: null, disabledAt: null);
    }

    public static function approvedAt(DateTimeImmutable $at): self
    {
        return new self(approvedAt: $at, disabledAt: null);
    }

    public function isActive(): bool
    {
        return !$this->isPending() && !$this->isDisabled();
    }

    public function isPending(): bool
    {
        return $this->approvedAt === null;
    }

    public function isDisabled(): bool
    {
        return $this->disabledAt !== null;
    }

    public function equals(self $other): bool
    {
        $sameApproval = self::sameInstant($this->approvedAt, $other->approvedAt);

        return $sameApproval && self::sameInstant($this->disabledAt, $other->disabledAt);
    }

    private static function sameInstant(?DateTimeImmutable $one, ?DateTimeImmutable $other): bool
    {
        return $one?->getTimestamp() === $other?->getTimestamp();
    }
}
