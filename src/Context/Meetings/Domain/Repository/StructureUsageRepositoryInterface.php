<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Repository;

use App\Context\Meetings\Domain\ValueObject\StructureImpact;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface StructureUsageRepositoryInterface
{
    /**
     * @return ReadableCollection<string, StructureImpact>
     */
    public function byService(LocalDate $today): ReadableCollection;

    /**
     * @param list<Uuid> $stageIds
     *
     * @return ReadableCollection<string, StructureImpact>
     */
    public function ofStages(array $stageIds, LocalDate $today): ReadableCollection;

    /**
     * @param list<Uuid> $scheduleIds
     *
     * @return list<string>
     */
    public function lockUnfinished(array $scheduleIds): array;
}
