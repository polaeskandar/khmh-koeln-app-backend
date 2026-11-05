<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

final readonly class MarkResult
{
    public function __construct(
        public MarkView $mark,
        public bool $created,
    ) {
    }
}
