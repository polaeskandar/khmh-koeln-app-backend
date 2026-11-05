<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Repository\RecentLessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\TaughtLesson;
use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

final readonly class RecentLessons
{
    public function __construct(
        private RecentLessonRepositoryInterface $lessons,
    ) {
    }

    /**
     * @param list<Uuid> $stageIds
     *
     * @return array<string, TaughtLesson>
     */
    public function lastTaughtIn(array $stageIds, LocalDate $today): array
    {
        return $this->lessons->lastTaughtIn($stageIds, $today);
    }
}
