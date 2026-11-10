<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Enum\LessonStatus;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Model\LocalDate;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class LessonState
{
    private function __construct(
        #[ORM\Column(name: 'status', length: 9, enumType: LessonStatus::class)]
        public LessonStatus $status,
        #[ORM\Column(name: 'completed_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $completedAt,
        #[ORM\Column(name: 'archived_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $archivedAt,
    ) {
    }

    public static function draft(): self
    {
        return new self(LessonStatus::Draft, completedAt: null, archivedAt: null);
    }

    public function archivedAt(DateTimeImmutable $at): self
    {
        return new self($this->status, $this->completedAt, $this->archivedAt ?? $at);
    }

    public function restored(): self
    {
        return new self($this->status, $this->completedAt, archivedAt: null);
    }

    public function ensureEditable(): void
    {
        if ($this->archivedAt !== null) {
            throw new RuleViolation('lessons.archived');
        }
    }

    public function withStatus(LessonStatus $status, DateTimeImmutable $at): self
    {
        return match (true) {
            $status === $this->status => $this,
            $status === LessonStatus::Completed => new self($status, $at, $this->archivedAt),
            default => new self($status, completedAt: null, archivedAt: $this->archivedAt),
        };
    }

    public function isCompletedBeforeTeachingDate(LocalDate $teachingDate, LocalDate $today): bool
    {
        return $this->status === LessonStatus::Completed && $today->isBefore($teachingDate);
    }
}
