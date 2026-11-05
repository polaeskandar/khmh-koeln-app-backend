<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface ScheduleRepositoryInterface
{
    public function save(Schedule $schedule): void;

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
     * @return ReadableCollection<int, Schedule>
     */
    public function active(): ReadableCollection;

    public function find(Uuid $id): ?Schedule;

    public function findRecurring(Uuid $id): ?Schedule;

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
