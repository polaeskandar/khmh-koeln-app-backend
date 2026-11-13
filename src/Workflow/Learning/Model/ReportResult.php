<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Domain\ValueObject\NamedAccounts;
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

    public static function of(CompletionOutcome $outcome, NamedAccounts $accounts, Uuid $viewerId): self
    {
        $by = $outcome->completedBy;
        $reporter = $accounts->find($by);

        return new self(
            created: $outcome->created,
            completedAt: $outcome->completedAt,
            completedBy: $reporter === null ? null : $accounts->nameOf($reporter),
            completedByYou: $by?->equals($viewerId) === true,
        );
    }
}
