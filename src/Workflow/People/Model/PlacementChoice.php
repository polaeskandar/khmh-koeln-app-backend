<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

final readonly class PlacementChoice
{
    public function __construct(
        public ?string $serviceId,
        public ?string $stageId,
    ) {
    }
}
