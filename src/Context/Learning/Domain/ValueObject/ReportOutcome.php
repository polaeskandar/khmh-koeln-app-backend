<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class ReportOutcome
{
    public function __construct(
        public bool $created,
        public DateTimeImmutable $completedAt,
        public ?Uuid $completedBy,
    ) {
    }
}
