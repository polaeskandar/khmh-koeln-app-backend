<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Privacy\Domain\ValueObject\OpenErasure;
use DateTimeImmutable;

final readonly class OwnErasureRequest
{
    public function __construct(
        public DateTimeImmutable $receivedAt,
        public string $dueOn,
    ) {
    }

    public static function of(OpenErasure $open): self
    {
        return new self($open->receivedAt, $open->dueOn);
    }
}
