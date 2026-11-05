<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

final readonly class AgendaFilter
{
    public function __construct(
        public ?string $from,
        public ?string $to,
        public ?string $serviceId,
        public ?string $stageId,
        public ?string $scheduleId,
    ) {
    }
}
