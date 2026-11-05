<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class HeldSessions
{
    public function __construct(
        public Uuid $closedId,
        public DateTimeImmutable $closedOpenedAt,
        public Uuid $openId,
        public DateTimeImmutable $openOpenedAt,
    ) {
    }
}
