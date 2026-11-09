<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Privacy\Domain\ValueObject\Deadlines;

final readonly class RequestDeadlines
{
    public function __construct(
        public string $dueOn,
        public ?string $erasesOn,
    ) {
    }

    public static function of(Deadlines $deadlines): self
    {
        return new self($deadlines->dueOn->toString(), $deadlines->erasesOn?->toString());
    }
}
