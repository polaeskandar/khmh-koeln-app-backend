<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\DescriptionChange;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ServiceStages
{
    public function __construct(
        private StageRepositoryInterface $stages,
        private ServiceRepositoryInterface $services,
        private StageNames $names,
    ) {
    }

    /**
     * @return ReadableCollection<int, Stage>
     */
    public function ofService(Service $service): ReadableCollection
    {
        return $this->stages->ofService($service);
    }

    public function find(string $id): Stage
    {
        return $this->stages->find(Uuid::fromString($id)) ?? throw NotFound::of('stage');
    }

    public function add(string $serviceId, string $name, ?string $description): Stage
    {
        return $this->services->withStructureLock(
            Uuid::fromString($serviceId),
            fn (Service $service): Stage => $this->addTo($service, $name, $description),
        );
    }

    public function edit(string $stageId, ?string $name, DescriptionChange $description): Stage
    {
        $serviceId = $this->stages->serviceIdOf(Uuid::fromString($stageId)) ?? throw NotFound::of('stage');

        return $this->services->withStructureLock($serviceId, fn (Service $service): Stage => $this->editIn(
            $service,
            $stageId,
            $name,
            $description,
        ));
    }

    private function editIn(Service $service, string $stageId, ?string $name, DescriptionChange $description): Stage
    {
        self::ensureOpen($service);

        $stage = $this->find($stageId);

        $stage->edit($name ?? $stage->name()->value, $description->appliedTo($stage->description()->value));
        $this->names->ensureFreeFor($stage);
        $this->stages->save($stage);

        return $stage;
    }

    private function addTo(Service $service, string $name, ?string $description): Stage
    {
        self::ensureOpen($service);

        $existing = $this->stages->ofService($service);
        $active = $existing->filter(static fn (Stage $stage): bool => !$stage->isArchived());
        $stage = new Stage($service, $name, $description, $active->count());
        $this->names->ensureFreeFor($stage);

        foreach ($existing->filter(static fn (Stage $held): bool => $held->isArchived()) as $archived) {
            $archived->moveTo($archived->position() + 1);
        }

        $this->stages->save($stage);

        return $stage;
    }

    private static function ensureOpen(Service $service): void
    {
        if ($service->isArchived()) {
            throw new RuleViolation('services.archivedNoStageChanges', ['name' => $service->name()->value]);
        }
    }
}
