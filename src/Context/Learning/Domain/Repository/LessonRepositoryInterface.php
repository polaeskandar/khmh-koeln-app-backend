<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Repository;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Query\LessonQueryInterface;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface LessonRepositoryInterface extends LessonQueryInterface
{
    public function save(Lesson $lesson): void;

    public function remove(Lesson $lesson): void;

    public function currentAt(Uuid $scheduleId, LocalDate $date): ?Lesson;

    /**
     * @param list<Uuid> $stageIds
     */
    public function countForStages(array $stageIds): int;

    /**
     * @return ReadableCollection<int, Lesson>
     */
    public function ofStage(Uuid $stageId): ReadableCollection;
}
