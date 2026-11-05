<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

final readonly class ErasureChoice
{
    public function __construct(
        public bool $clearNotes,
    ) {
    }
}
