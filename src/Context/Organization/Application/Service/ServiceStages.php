<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ServiceStages
{
    public function __construct(
        private StageRepositoryInterface $stages,
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
}
