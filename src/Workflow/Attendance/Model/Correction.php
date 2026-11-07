<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Workflow\People\Model\PersonReference;

final readonly class Correction
{
    public function __construct(
        public string $id,
        public PersonReference $person,
        public MarkHistory $recorded,
        public MarkHistory $removed,
        public ?string $reason,
    ) {
    }
}
