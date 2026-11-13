<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Repository;

use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface ScheduleRepositoryInterface
{
    public function save(Schedule $schedule): void;

    public function replanUpcoming(Schedule $schedule, LocalDate $from): void;

    public function remove(Schedule $schedule): void;

    /**
     * @param callable(Schedule): void $work
     */
    public function withWriteLock(Uuid $id, callable $work): void;

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function withShareLock(Uuid $id, callable $work): mixed;

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
