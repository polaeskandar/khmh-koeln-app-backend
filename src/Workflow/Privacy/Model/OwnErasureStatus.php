<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Privacy\Domain\ValueObject\OpenErasure;

final readonly class OwnErasureStatus
{
    public function __construct(
        public ?OwnErasureRequest $erasureRequested,
    ) {
    }

    public static function of(?OpenErasure $open): self
    {
        return new self($open === null ? null : OwnErasureRequest::of($open));
    }
}
