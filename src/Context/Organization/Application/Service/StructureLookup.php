<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Enum\ServiceFilter;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class StructureLookup
{
    public function __construct(
        private ServiceRepositoryInterface $services,
        private StageRepositoryInterface $stages,
    ) {
    }

    /**
     * @return ReadableCollection<int, Service>
     */
    public function listed(string $status): ReadableCollection
    {
        $filter = ServiceFilter::tryFrom($status) ?? throw InvalidInput::field('status', 'validation.invalidChoice');

        return $this->services->listed($filter);
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Service>
     */
    public function services(array $ids): ReadableCollection
    {
        return $this->services->findByIds($ids);
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Stage>
     */
    public function stages(array $ids): ReadableCollection
    {
        return $this->stages->findByIds($ids);
    }

    public function stage(Uuid $id): Stage
    {
        return $this->stages->find($id) ?? throw NotFound::of('stage');
    }

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function whileStageKept(Uuid $stageId, callable $work): mixed
    {
        $serviceId = $this->stages->serviceIdOf($stageId) ?? throw NotFound::of('stage');

        return $this->services->withShareLock([$serviceId], $work);
    }

    /**
     * @return list<Uuid>
     */
    public function archivedIds(): array
    {
        $services = $this->services->listed(ServiceFilter::Archived)->map(
            static fn (Service $service): Uuid => $service->id(),
        );

        $stages = $this->stages->archived()->map(static fn (Stage $stage): Uuid => $stage->id());

        return [...$services->getValues(), ...$stages->getValues()];
    }
}
