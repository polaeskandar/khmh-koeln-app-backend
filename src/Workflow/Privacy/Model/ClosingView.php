<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Workflow\Identity\Model\AccountReference;
use DateTimeImmutable;

final readonly class ClosingView
{
    /**
     * @param array<string, int>|null $outcome
     */
    public function __construct(
        public ?DateTimeImmutable $closedAt,
        public ?AccountReference $closedBy,
        public ?string $declineReason,
        public ?array $outcome,
    ) {
    }
}
