<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\StageLineup;
use App\Context\Organization\Domain\ValueObject\StructureUse;
use App\Library\Problem\Exception\NotFound;
use Symfony\Component\Uid\Uuid;

final readonly class StructureRemovals
{
    public function __construct(
        private ServiceRepositoryInterface $services,
        private StageRepositoryInterface $stages,
        private StageOrder $order,
    ) {
    }

    /**
     * @param callable(Service, list<Uuid>): StructureUse $release
     */
    public function removeService(string $id, callable $release): void
    {
        $serviceId = Uuid::isValid($id) ? Uuid::fromString($id) : throw NotFound::of('service');

        $this->services->withStructureLock($serviceId, function (Service $service) use ($release): Service {
            $stages = $this->stages->ofService($service);
            $stageIds = $stages->map(static fn (Stage $stage): Uuid => $stage->id())->getValues();
            $release($service, $stageIds)->ensureUnused('services', $service->name()->value);

            foreach ($stages as $stage) {
                $this->stages->remove($stage);
            }

            $this->services->remove($service);

            return $service;
        });
    }

    /**
     * @param callable(Stage): StructureUse $release
     */
    public function removeStage(string $id, callable $release): void
    {
        $stageId = Uuid::isValid($id) ? Uuid::fromString($id) : throw NotFound::of('stage');
        $serviceId = $this->stages->serviceIdOf($stageId) ?? throw NotFound::of('stage');

        $this->services->withStructureLock($serviceId, function (Service $service) use ($stageId, $release): Stage {
            StageChecks::ensureOpen($service);
            $stage = $this->stages->find($stageId) ?? throw NotFound::of('stage');
            $release($stage)->ensureUnused('stages', $stage->name()->value);
            $this->stages->remove($stage);
            $this->order->renumber(StageLineup::besides($stage, $this->stages->ofService($service))->others());

            return $stage;
        });
    }
}
