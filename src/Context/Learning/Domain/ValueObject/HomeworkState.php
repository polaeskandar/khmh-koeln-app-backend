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

    public function published(DateTimeImmutable $at): self
    {
        return match (true) {
            $this->archivedAt !== null => throw new RuleViolation('homework.archivedNoPublish'),
            $this->status === HomeworkStatus::Published => $this,
            default => new self(HomeworkStatus::Published, $at, $this->archivedAt),
        };
    }

    public function archived(DateTimeImmutable $at): self
    {
        return new self(HomeworkStatus::Archived, $this->publishedAt, $this->archivedAt ?? $at);
    }

    public function drafted(): self
    {
        return $this->status === HomeworkStatus::Published
            ? new self(HomeworkStatus::Draft, $this->publishedAt, $this->archivedAt)
            : throw new RuleViolation('homework.onlyPublishedBack');
    }

    public function restored(): self
    {
        return match (true) {
            $this->archivedAt === null => throw new RuleViolation('homework.notArchived'),
            $this->publishedAt === null => new self(HomeworkStatus::Draft, publishedAt: null, archivedAt: null),
            default => new self(HomeworkStatus::Published, $this->publishedAt, archivedAt: null),
        };
    }

    public function ensureNeverPublished(): void
    {
        if ($this->publishedAt !== null) {
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
