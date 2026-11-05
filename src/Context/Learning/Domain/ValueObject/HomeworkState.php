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

    public function publishedAt(DateTimeImmutable $at): self
    {
        return new self(HomeworkStatus::Published, $at, $this->archivedAt);
    }

    public function ensureNotArchived(string $refusal): void
    {
        if ($this->archivedAt !== null) {
            throw new RuleViolation($refusal);
        }
    }
}
