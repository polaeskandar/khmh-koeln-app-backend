<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Query;

use App\Context\Scheduling\Domain\ValueObject\StructureImpact;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface StructureUsageQueryInterface
{
    /**
     * @return ReadableCollection<string, StructureImpact>
     */
    public function listImpactByService(LocalDate $today): ReadableCollection;

    /**
     * @param list<Uuid> $stageIds
     *
     * @return ReadableCollection<string, StructureImpact>
     */
    public function listImpactOfStages(array $stageIds, LocalDate $today): ReadableCollection;
}
