<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Workflow\Identity\Model\AccountReference;

final readonly class ClosingView
{
    /**
     * @param array<string, int>|null $outcome
     */
    public function __construct(
        public ?string $closedAt,
        public ?AccountReference $closedBy,
        public ?string $declineReason,
        public ?array $outcome,
    ) {
    }
}
