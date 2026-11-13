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
        #[ORM\Column(name: 'disabled_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $disabledAt,
        #[ORM\Column(name: 'auth_version', options: ['default' => 0])]
        public int $authVersion = 0,
    ) {
    }

    public static function active(): self
    {
        return new self(disabledAt: null);
    }

    public function disabledAt(DateTimeImmutable $at): self
    {
        if ($this->disabledAt !== null) {
            return $this;
        }

        return new self($at, $this->authVersion + 1);
    }

    public function enabled(): self
    {
        return new self(disabledAt: null, authVersion: $this->authVersion);
    }

    public function isActive(): bool
    {
        return $this->disabledAt === null;
    }
}
