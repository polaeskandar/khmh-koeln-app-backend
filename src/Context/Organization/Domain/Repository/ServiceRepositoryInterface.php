<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Repository;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Enum\ServiceFilter;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface ServiceRepositoryInterface
{
    public function save(Service $service): void;

    /**
     * @return ReadableCollection<int, int>
     */
    public function tonesInUse(): ReadableCollection;

    public function find(Uuid $id): ?Service;

    public function findByNameKey(string $key): ?Service;

    /**
     * @return ReadableCollection<int, Service>
     */
    public function listed(ServiceFilter $filter): ReadableCollection;
}
