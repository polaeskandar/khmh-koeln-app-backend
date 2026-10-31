<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Repository;

use App\Context\Organization\Domain\Entity\ChurchService;
use Doctrine\Common\Collections\ReadableCollection;

interface ChurchServiceRepositoryInterface
{
    public function save(ChurchService $service): void;

    /**
     * @return ReadableCollection<int, int>
     */
    public function tonesInUse(): ReadableCollection;
}
