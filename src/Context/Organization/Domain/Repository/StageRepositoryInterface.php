<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Repository;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface StageRepositoryInterface
{
    public function save(Stage $stage): void;

    public function belongsTo(Uuid $stageId, Uuid $serviceId): bool;

    /**
     * @param ReadableCollection<int, Service> $services
     *
     * @return ReadableCollection<int, Stage>
     */
    public function activeIn(ReadableCollection $services): ReadableCollection;
}
