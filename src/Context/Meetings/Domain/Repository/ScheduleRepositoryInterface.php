<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use Symfony\Component\Uid\Uuid;

interface ScheduleRepositoryInterface
{
    public function save(Schedule $schedule): void;

    /**
     * @return list<Uuid>
     */
    public function activeIds(): array;
}
