<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Meetings\Model\SessionState;
use DateTimeImmutable;

final readonly class SessionStateResponse
{
    public function __construct(
        public string $id,
        public SessionStatus $status,
        public ?DateTimeImmutable $openedAt,
        public ?DateTimeImmutable $closedAt,
        public ?AccountReference $closedBy,
    ) {
    }

    public static function of(SessionState $state): self
    {
        return new self($state->id, $state->status, $state->openedAt, $state->closedAt, $state->closedBy);
    }
}
