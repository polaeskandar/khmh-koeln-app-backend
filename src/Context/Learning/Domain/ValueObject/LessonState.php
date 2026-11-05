<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Enum\LessonStatus;
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
        return new self($this->status, $this->completedAt, $at);
    }
}
