<?php

declare(strict_types=1);

namespace App\Library\Time\Model;

use Carbon\CarbonImmutable;

final readonly class Period
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
    }
}
