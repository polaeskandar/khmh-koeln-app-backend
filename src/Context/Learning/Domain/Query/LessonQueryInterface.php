<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Query;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface LessonQueryInterface
{
    public function find(Uuid $id): ?Lesson;

    public function countForSchedule(Uuid $scheduleId): int;

    /**
     * @param list<Uuid> $scheduleIds
     *
     * @return ReadableCollection<int, Lesson>
     */
    public function listCurrentBetween(array $scheduleIds, LocalDate $from, LocalDate $to): ReadableCollection;
}
