<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Enum\ServiceFilter;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
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
