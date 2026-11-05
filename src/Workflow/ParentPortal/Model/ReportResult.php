<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Learning\Domain\ValueObject\CompletionOutcome;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class ReportResult
{
    public function __construct(
        public bool $created,
        public DateTimeImmutable $completedAt,
        public ?string $completedBy,
        public bool $completedByYou,
    ) {
    }

    public static function of(CompletionOutcome $outcome, ?User $reporter, Uuid $viewerId): self
    {
        $by = $outcome->completedBy;

        return new self(
            created: $outcome->created,
            completedAt: $outcome->completedAt,
            completedBy: $reporter?->profile()->displayName->value,
            completedByYou: $by?->equals($viewerId) === true,
        );
    }
}
