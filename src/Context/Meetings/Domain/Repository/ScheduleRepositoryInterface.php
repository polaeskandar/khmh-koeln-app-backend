<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use Doctrine\Common\Collections\ReadableCollection;

interface ScheduleRepositoryInterface
{
    public function save(Schedule $schedule): void;

    /**
     * @return ReadableCollection<int, Schedule>
     */
    public function active(): ReadableCollection;
}
