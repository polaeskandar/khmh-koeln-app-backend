<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Workflow\Identity\Model\AccountReference;

final readonly class MarkReference
{
    public function __construct(
        public string $id,
        public string $recordedAt,
        public ?AccountReference $recordedBy,
    ) {
    }
}
