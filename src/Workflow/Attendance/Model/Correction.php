<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\People\Model\PersonReference;

final readonly class Correction
{
    public function __construct(
        public MarkReference $mark,
        public PersonReference $person,
        public string $removedAt,
        public ?AccountReference $removedBy,
        public ?string $reason,
    ) {
    }
}
