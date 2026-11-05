<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Request;

use App\Workflow\People\Model\PlacementChoice;

final readonly class PlacementRequest
{
    public function __construct(
        public ?string $serviceId = null,
        public ?string $stageId = null,
    ) {
    }

    public function choice(): PlacementChoice
    {
        return new PlacementChoice($this->serviceId, $this->stageId);
    }
}
