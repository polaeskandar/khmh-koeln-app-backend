<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Repository\RosterRepositoryInterface;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class Rosters
{
    public function __construct(
        private RosterRepositoryInterface $rosters,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param list<Uuid> $serviceIds
     *
     * @return ReadableCollection<string, array{members: int, serviceLevel: int}>
     */
    public function serviceMembersToday(array $serviceIds): ReadableCollection
    {
        return $this->rosters->serviceMemberCounts($serviceIds, $this->calendar->today());
    }

    /**
     * @param list<Uuid> $stageIds
     *
     * @return ReadableCollection<string, int>
     */
    public function stageMembersToday(array $stageIds): ReadableCollection
    {
        return $this->rosters->stageMemberCounts($stageIds, $this->calendar->today());
    }
}
