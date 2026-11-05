<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Privacy\Domain\ValueObject\OpenErasure;

final readonly class OwnErasureRequest
{
    public function __construct(
        public string $receivedAt,
        public string $dueOn,
    ) {
    }

    public static function of(OpenErasure $open): self
    {
        return new self($open->receivedAt, $open->dueOn);
    }
}
