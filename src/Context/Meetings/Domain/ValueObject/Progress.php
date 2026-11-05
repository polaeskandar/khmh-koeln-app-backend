<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Model\LocalDate;
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

    public function opened(Stamp $stamp, LocalDate $occurrenceDate, LocalDate $today): self
    {
        return match (true) {
            $this->status === SessionStatus::Active => $this,
            $this->status === SessionStatus::Canceled => throw new RuleViolation('meetings.sessionCanceled'),
            $this->status === SessionStatus::Completed => throw new RuleViolation('meetings.attendanceClosed'),
            $today->isBefore($occurrenceDate) => throw new RuleViolation('meetings.opensOnTheDay'),
            default => new self(SessionStatus::Active, $this->cancelReason, $stamp, Stamp::none()),
        };
    }

    public function closed(Stamp $stamp): self
    {
        return match ($this->status) {
            SessionStatus::Completed => $this,
            SessionStatus::Active => new self(SessionStatus::Completed, $this->cancelReason, $this->opened, $stamp),
            default => throw new RuleViolation('meetings.onlyActiveCanClose'),
        };
    }

    public function reopened(): self
    {
        return match ($this->status) {
            SessionStatus::Active => $this,
            SessionStatus::Completed => new self(
                SessionStatus::Active,
                $this->cancelReason,
                $this->opened,
                Stamp::none(),
            ),
            default => throw new RuleViolation('meetings.onlyCompletedCanReopen'),
        };
    }

    public function canceled(?string $reason): self
    {
        return $this->status === SessionStatus::Scheduled
            ? new self(SessionStatus::Canceled, $reason, $this->opened, $this->closed)
            : throw new RuleViolation('meetings.onlyScheduledCanCancel');
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
