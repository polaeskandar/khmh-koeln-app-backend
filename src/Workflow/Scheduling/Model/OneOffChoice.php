<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

final readonly class OneOffChoice
{
    public function __construct(
        public RosterChoice $roster,
        public ?string $title,
        public ?string $location,
        public ?string $startsAt,
        public ?string $endsAt,
    ) {
    }
}
