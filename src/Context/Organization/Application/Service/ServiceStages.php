<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Context\Organization\Domain\Validation\ArchiveRule;
use App\Context\Organization\Domain\ValueObject\Description;
use App\Context\Organization\Domain\ValueObject\StageName;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ServiceStages
{
    public function __construct(
        private StageRepositoryInterface $stages,
        private ServiceRepositoryInterface $services,
        private StageChecks $checks,
    ) {
    }

    /**
     * @param ReadableCollection<int, Service> $services
     *
     * @return ReadableCollection<int, Stage>
     */
    public function activeIn(ReadableCollection $services): ReadableCollection
    {
        return $this->stages->activeIn($services);
    }

    /**
     * @return ReadableCollection<int, Stage>
     */
    public function activeOf(Service $service): ReadableCollection
    {
        return $this->stages->activeIn(new ArrayCollection([$service]));
    }

    /**
     * @return ReadableCollection<int, Stage>
     */
    public function ofService(Service $service): ReadableCollection
    {
        return $this->stages->ofService($service);
    }

    public function find(Uuid $id): Stage
    {
        return $this->stages->find($id) ?? throw NotFound::of('stage');
    }

    public function add(Uuid $serviceId, string $name, ?string $description): Stage
    {
        StageChecks::check($name, $description);

        return $this->services->withWriteLock($serviceId, fn (Service $service): Stage => $this->addTo(
            $service,
            StageName::of($name),
            StageChecks::description($description),
        ));
    }

    public function edit(Uuid $stageId, string $name, ?string $description): Stage
    {
        $lockedId = $this->stages->serviceIdOf($stageId) ?? throw NotFound::of('stage');
        StageChecks::check($name, $description);

        return $this->services->withWriteLock($lockedId, fn (Service $service): Stage => $this->editIn(
            $service,
            $stageId,
            StageName::of($name),
            StageChecks::description($description),
        ));
    }

    private function editIn(Service $service, Uuid $stageId, StageName $name, Description $description): Stage
    {
        ArchiveRule::ensureStagesChangeable($service);

        $stage = $this->find($stageId);

        $this->checks->ensureFree($service, $name, $stage);
        $stage->edit($name, $description);
        $this->stages->save($stage);

        return $stage;
    }

    private function addTo(Service $service, StageName $name, Description $description): Stage
    {
        ArchiveRule::ensureStagesChangeable($service);
        $this->checks->ensureFree($service, $name, stage: null);

        $existing = $this->stages->ofService($service);
        $active = $existing->filter(static fn (Stage $stage): bool => !$stage->isArchived());
        $stage = new Stage($service, $name, $description, $active->count());

        foreach ($existing->filter(static fn (Stage $held): bool => $held->isArchived()) as $archived) {
            $archived->moveTo($archived->position() + 1);
        }

        $this->stages->save($stage);

        return $stage;
    }
}
