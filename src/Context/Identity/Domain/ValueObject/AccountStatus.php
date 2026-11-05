<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Library\Problem\Exception\RuleViolation;
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
        #[ORM\Column(name: 'auth_version', options: ['default' => 0])]
        public int $authVersion = 0,
        #[ORM\Column(name: 'email_confirmed_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $emailConfirmedAt = null,
    ) {
    }

    public static function pending(): self
    {
        return new self(approvedAt: null, disabledAt: null);
    }

    public function approvedOn(DateTimeImmutable $at): self
    {
        if ($this->isDisabled()) {
            throw new RuleViolation('accounts.requestDisabled');
        }

        return new self($at, $this->disabledAt, $this->authVersion, $this->emailConfirmedAt ?? $at);
    }

    public function emailConfirmedOn(DateTimeImmutable $at): self
    {
        return new self($this->approvedAt, $this->disabledAt, $this->authVersion, $this->emailConfirmedAt ?? $at);
    }

    public function disabledAt(DateTimeImmutable $at): self
    {
        if ($this->isDisabled()) {
            return $this;
        }

        return new self($this->approvedAt, $at, $this->authVersion + 1, $this->emailConfirmedAt);
    }

    public function enabled(): self
    {
        return new self(
            $this->approvedAt,
            disabledAt: null,
            authVersion: $this->authVersion,
            emailConfirmedAt: $this->emailConfirmedAt,
        );
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

    public function isEmailConfirmed(): bool
    {
        return $this->emailConfirmedAt !== null;
    }
}
