<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Repository;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface LessonRepositoryInterface
{
    public function save(Lesson $lesson): void;

    public function remove(Lesson $lesson): void;

    public function currentAt(Uuid $scheduleId, LocalDate $date): ?Lesson;

    public function find(Uuid $id): ?Lesson;

    public function countForSchedule(Uuid $scheduleId): int;

    /**
     * @param list<Uuid> $stageIds
     */
    public function countForStages(array $stageIds): int;

    /**
     * @return ReadableCollection<int, Lesson>
     */
    public function ofStage(Uuid $stageId): ReadableCollection;

    /**
     * @param list<Uuid> $scheduleIds
     *
     * @return ReadableCollection<int, Lesson>
     */
    public function currentBetween(array $scheduleIds, LocalDate $from, LocalDate $to): ReadableCollection;
}
