<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Progress
{
    private function __construct(
        #[ORM\Column(name: 'status', length: 9, enumType: SessionStatus::class)]
        public SessionStatus $status,
        #[ORM\Column(name: 'cancel_reason', length: 200, nullable: true)]
        public ?string $cancelReason,
        #[ORM\Embedded(Stamp::class, columnPrefix: 'opened_')]
        public Stamp $opened,
        #[ORM\Embedded(Stamp::class, columnPrefix: 'closed_')]
        public Stamp $closed,
    ) {
    }

    public static function scheduled(): self
    {
        return new self(SessionStatus::Scheduled, cancelReason: null, opened: Stamp::none(), closed: Stamp::none());
    }

    public function withStatus(SessionStatus $status): self
    {
        return new self($status, $this->cancelReason, $this->opened, $this->closed);
    }

    public function withOpened(Stamp $opened): self
    {
        return new self($this->status, $this->cancelReason, $opened, $this->closed);
    }

    public function withClosed(Stamp $closed): self
    {
        return new self($this->status, $this->cancelReason, $this->opened, $closed);
    }

    public function withCancelReason(?string $reason): self
    {
        return new self($this->status, $reason, $this->opened, $this->closed);
    }

    public function ensureNotCanceled(): void
    {
        if ($this->status === SessionStatus::Canceled) {
            throw new RuleViolation('meetings.sessionCanceled');
        }
    }

    public function wasOpened(): bool
    {
        return $this->status === SessionStatus::Active || $this->status === SessionStatus::Completed;
    }
}
