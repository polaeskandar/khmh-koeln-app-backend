<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Privacy\Domain\ValueObject\ErasureRun;

final readonly class DueErasures
{
    public function __construct(
        public int $erased,
        public int $failed,
    ) {
    }

    public static function of(ErasureRun $run): self
    {
        return new self($run->erased, $run->failed);
    }
}
