<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Entity\Session;
use Symfony\Component\Uid\Uuid;

final readonly class CoveredSession
{
    public function __construct(
        public Uuid $id,
        public string $date,
        public string $meeting,
    ) {
    }

    public static function of(Session $session): self
    {
        return new self($session->id(), $session->occurrenceDate()->toString(), $session->schedule()->details()->name);
    }
}
