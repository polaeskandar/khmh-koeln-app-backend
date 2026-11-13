<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\Enum\SessionStatus;

final readonly class SessionProgress
{
    public function __construct(
        public SessionStatus $status,
        public ?string $cancelReason,
    ) {
    }

    public static function of(Session $session): self
    {
        $progress = $session->progress();

        return new self(status: $progress->status, cancelReason: $progress->cancelReason);
    }

    public function withoutCancelReason(): self
    {
        return new self($this->status, cancelReason: null);
    }
}
