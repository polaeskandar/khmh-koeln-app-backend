<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\ValueObject\ChosenPlacement;

final readonly class PlacementChoices
{
    public function __construct(
        private ServiceChoices $services,
        private StageChoices $stages,
    ) {
    }

    public function of(?string $serviceId, ?string $stageId, string $fieldPrefix): ChosenPlacement
    {
        $service = $this->services->of($serviceId, field: $fieldPrefix . 'serviceId');

        return new ChosenPlacement($service, $this->stages->of($stageId, $service, field: $fieldPrefix . 'stageId'));
    }
}
