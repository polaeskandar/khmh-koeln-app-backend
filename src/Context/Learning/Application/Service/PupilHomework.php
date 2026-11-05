<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\Learning\Domain\Repository\HomeworkReportRepositoryInterface;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\Pupil;
use App\Context\Learning\Domain\ValueObject\PupilTasks;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class PupilHomework
{
    public function __construct(
        private HomeworkRepositoryInterface $homework,
        private HomeworkReportRepositoryInterface $reports,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param list<Pupil> $pupils
     *
     * @return list<PupilTasks>
     */
    public function of(array $pupils): array
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

        $completedAt = Collections::firstPerKey($reports, keyOf: self::reportKey(...))->map(
            fn (HomeworkCompletion $report): string => $this->calendar->formatInstant($report->completedAt()),
        );

        return $all->map(
            static fn (Pupil $pupil): PupilTasks => PupilTasks::of($pupil, $published, $reports, $completedAt),
        )->getValues();
    }

    private static function reportKey(HomeworkCompletion $report): string
    {
        return $report->id()->toRfc4122();
    }
}
