<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface ScheduleRepositoryInterface
{
    public function save(Schedule $schedule): void;

    public function replanUpcoming(Schedule $schedule, LocalDate $from): void;

    /**
     * @return list<Uuid>
     */
    public function activeIds(): array;

    public function find(Uuid $id): ?Schedule;

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Schedule>
     */
    public function findByIds(array $ids): ReadableCollection;

    /**
     * @return ReadableCollection<int, Schedule>
     */
    public function listed(?Uuid $serviceId, ?Uuid $stageId): ReadableCollection;
}
