<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Repository;

use App\Context\Organization\Domain\Entity\Service;
use Doctrine\Common\Collections\ReadableCollection;

interface ServiceRepositoryInterface
{
    public function save(Service $service): void;

    /**
     * @return ReadableCollection<int, int>
     */
    public function tonesInUse(): ReadableCollection;

    /**
     * @return ReadableCollection<int, Service>
     */
    public function active(): ReadableCollection;
}
