<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Library\Problem\Exception\RuleViolation;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class HomeworkState
{
    private function __construct(
        #[ORM\Column(name: 'status', length: 9, enumType: HomeworkStatus::class)]
        public HomeworkStatus $status,
        #[ORM\Column(name: 'published_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $publishedAt,
        #[ORM\Column(name: 'archived_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $archivedAt,
    ) {
    }

    public static function draft(): self
    {
        return new self(HomeworkStatus::Draft, publishedAt: null, archivedAt: null);
    }

    public function withStatus(HomeworkStatus $status): self
    {
        return new self($status, $this->publishedAt, $this->archivedAt);
    }

    public function withPublishedAt(DateTimeImmutable $at): self
    {
        return new self($this->status, $at, $this->archivedAt);
    }

    public function withArchivedAt(?DateTimeImmutable $at): self
    {
        return new self($this->status, $this->publishedAt, $at);
    }

    public function wasPublished(): bool
    {
        return $this->publishedAt !== null;
    }

    public function ensureNeverPublished(): void
    {
        if ($this->wasPublished()) {
            throw new RuleViolation('homework.publishedStays');
        }
    }

    public function ensureEditable(): void
    {
        if ($this->archivedAt !== null) {
            throw new RuleViolation('homework.archivedNoEdit');
        }
    }
}
