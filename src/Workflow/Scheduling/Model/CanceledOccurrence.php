<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\Entity\Session;

final readonly class CanceledOccurrence
{
    public function __construct(
        public string $sessionId,
    ) {
    }

    public static function of(Session $session): self
    {
        return new self($session->id()->toRfc4122());
    }
}
