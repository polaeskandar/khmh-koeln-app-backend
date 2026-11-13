<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Workflow\Identity\Model\AccountReference;
use DateTimeImmutable;

final readonly class MarkHistory
{
    public function __construct(
        public ?DateTimeImmutable $at,
        public ?AccountReference $by,
    ) {
    }
}
