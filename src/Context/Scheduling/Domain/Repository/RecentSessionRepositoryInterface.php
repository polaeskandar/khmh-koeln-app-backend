<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Repository;

use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Context\Scheduling\Domain\Entity\Session;
use Doctrine\Common\Collections\ReadableCollection;

interface RecentSessionRepositoryInterface
{
    /**
     * @param ReadableCollection<int, Schedule> $schedules
     * @param positive-int $limit
     *
     * @return ReadableCollection<int, Session> the latest completed sessions of the schedules, oldest first
     */
    public function latestCompleted(ReadableCollection $schedules, int $limit): ReadableCollection;
}
