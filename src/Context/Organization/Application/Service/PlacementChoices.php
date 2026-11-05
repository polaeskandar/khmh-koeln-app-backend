<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\ChosenPlacement;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\NameKey;

final readonly class PlacementChoices
{
    public function __construct(
        private ServiceChoices $services,
        private StageChoices $stages,
        private ServiceRepositoryInterface $serviceRecords,
        private StageRepositoryInterface $stageRecords,
    ) {
    }

    public function of(?string $serviceId, ?string $stageId, string $fieldPrefix): ChosenPlacement
    {
        $service = $this->services->of($serviceId, field: $fieldPrefix . 'serviceId');

        return new ChosenPlacement($service, $this->stages->of($stageId, $service, field: $fieldPrefix . 'stageId'));
    }

    public function inService(string $serviceId, ?string $stageId, string $field): ChosenPlacement
    {
        $service = $this->services->of($serviceId, field: $field);

        return new ChosenPlacement($service, $this->stages->of($stageId, $service, field: $field));
    }

    public function named(string $serviceName, string $stageName): ChosenPlacement
    {
        $found = $this->serviceRecords->findByNameKey(NameKey::of($serviceName)) ?? throw InvalidInput::field(
            'service',
            'meetings.chooseService',
        );

        $service = $this->services->of($found->id()->toRfc4122(), field: 'service');

        return new ChosenPlacement($service, $this->stageNamed($service, $stageName));
    }

    private function stageNamed(Service $service, string $name): ?Stage
    {
        if (InputText::of($name)->trimmed()->isEmpty()) {
            return null;
        }

        $found = $this->stageRecords->findInService($service, NameKey::of($name)) ?? throw InvalidInput::field(
            'stage',
            'meetings.chooseStage',
        );

        return $this->stages->of($found->id()->toRfc4122(), $service, field: 'stage');
    }
}
