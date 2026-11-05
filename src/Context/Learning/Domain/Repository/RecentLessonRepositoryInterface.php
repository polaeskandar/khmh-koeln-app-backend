<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Repository;

use App\Context\Learning\Domain\ValueObject\TaughtLesson;
use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

interface RecentLessonRepositoryInterface
{
    /**
     * @param list<Uuid> $stageIds
     *
     * @return array<string, TaughtLesson> each stage's latest lesson on or before today that is neither a draft nor
     *     archived, by stage id
     */
    public function lastTaughtIn(array $stageIds, LocalDate $today): array;
}
