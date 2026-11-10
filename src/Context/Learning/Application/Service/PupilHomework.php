<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Repository\HomeworkReportRepositoryInterface;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\Pupil;
use App\Context\Learning\Domain\ValueObject\PupilTasks;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class PupilHomework
{
    public function __construct(
        private HomeworkRepositoryInterface $homework,
        private HomeworkReportRepositoryInterface $reports,
    ) {
    }

    /**
     * @param list<Pupil> $pupils
     *
     * @return list<PupilTasks>
     */
    public function listTasksOf(array $pupils): array
    {
        $all = new ArrayCollection($pupils);

        $stageIds = $all->reduce(
            /**
             * @param list<Uuid> $ids
             *
             * @return list<Uuid>
             */
            static fn (array $ids, Pupil $pupil): array => [...$ids, ...$pupil->stageIds],
            initial: [],
        );

        $published = $this->homework->publishedInStages($stageIds);

        $reports = $this->reports->currentReportsAbout(
            $all->map(static fn (Pupil $pupil): Uuid => $pupil->personId)->getValues(),
        );

        return $all->map(
            static fn (Pupil $pupil): PupilTasks => PupilTasks::of($pupil, $published, $reports),
        )->getValues();
    }
}
