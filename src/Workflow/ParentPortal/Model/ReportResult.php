<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Learning\Domain\ValueObject\CompletionOutcome;
use Symfony\Component\Uid\Uuid;

final readonly class ReportResult
{
    public function __construct(
        public bool $created,
        public string $completedAt,
        public ?string $completedBy,
        public bool $completedByYou,
    ) {
    }

    public static function of(CompletionOutcome $outcome, AccountLookup $accounts, Uuid $viewerId): self
    {
        $by = $outcome->completedBy;
        $reporter = $by === null ? null : $accounts->account($by);

        return new self(
            created: $outcome->created,
            completedAt: $outcome->completedAt,
            completedBy: $reporter?->profile()->displayName->value,
            completedByYou: $by?->equals($viewerId) === true,
        );
    }
}
