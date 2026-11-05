<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class CurrentReport
{
    public function __construct(
        public DateTimeImmutable $completedAt,
        public ?Uuid $completedBy,
    ) {
    }
}
