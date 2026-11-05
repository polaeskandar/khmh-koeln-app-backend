<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Repository\StructureUsageRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\StructureImpact;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class StructureUsage
{
    public function __construct(
        private StructureUsageRepositoryInterface $usage,
        private Calendar $calendar,
    ) {
    }

    /**
     * @return ReadableCollection<string, StructureImpact>
     */
    public function byService(): ReadableCollection
    {
        return $this->usage->byService($this->calendar->today());
    }

    /**
     * @param list<Uuid> $stageIds
     *
     * @return ReadableCollection<string, StructureImpact>
     */
    public function ofStages(array $stageIds): ReadableCollection
    {
        return $this->usage->ofStages($stageIds, $this->calendar->today());
    }
}
