<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

final readonly class AgendaChoice
{
    public function __construct(
        public ?string $serviceId = null,
        public ?string $stageId = null,
        public ?string $scheduleId = null,
    ) {
    }
}
