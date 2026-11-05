<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

final readonly class RosterChoice
{
    public function __construct(
        public ?string $serviceId,
        public ?string $stageId,
        public ?string $mode,
    ) {
    }
}
