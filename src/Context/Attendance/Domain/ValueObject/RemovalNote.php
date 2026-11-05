<?php

declare(strict_types=1);

namespace App\Context\Attendance\Domain\ValueObject;

final readonly class RemovalNote
{
    public function __construct(
        public string $id,
        public string $text,
        public string $sessionId,
    ) {
    }
}
