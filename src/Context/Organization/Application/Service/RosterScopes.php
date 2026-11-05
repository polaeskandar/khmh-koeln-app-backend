<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\ChosenPlacement;
use App\Library\Problem\Exception\NotFound;
use Symfony\Component\Uid\Uuid;

final readonly class RosterScopes
{
    public function __construct(
        private ServiceRepositoryInterface $services,
        private StageRepositoryInterface $stages,
    ) {
    }

    public function of(?string $serviceId, ?string $stageId): ?ChosenPlacement
    {
        if ($stageId !== null) {
            $stage = $this->stages->find(Uuid::fromString($stageId)) ?? throw NotFound::of('stage');

            return new ChosenPlacement($stage->service(), $stage);
        }

        return $serviceId === null
            ? null
            : new ChosenPlacement(
                $this->services->find(Uuid::fromString($serviceId)) ?? throw NotFound::of('service'),
                stage: null,
            );
    }
}
