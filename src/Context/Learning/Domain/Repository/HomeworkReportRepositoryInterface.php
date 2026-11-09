<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Repository;

use App\Context\Learning\Domain\Entity\HomeworkReport;
use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\ValueObject\CurrentReport;
use DateTimeImmutable;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface HomeworkReportRepositoryInterface
{
    /**
     * @param list<HomeworkTask> $tasks
     *
     * @return ReadableCollection<int, HomeworkReport>
     */
    public function currentReportsOf(array $tasks): ReadableCollection;

    /**
     * @param list<Uuid> $personIds
     *
     * @return ReadableCollection<int, HomeworkReport>
     */
    public function currentReportsAbout(array $personIds): ReadableCollection;

    /**
     * @param list<Uuid> $taskIds
     *
     * @return ReadableCollection<int, string>
     */
    public function reportedAmong(array $taskIds): ReadableCollection;

    public function reportDone(Uuid $taskId, Uuid $personId, DateTimeImmutable $at, ?Uuid $by): bool;

    public function currentReport(Uuid $taskId, Uuid $personId): ?CurrentReport;

    public function undo(Uuid $taskId, Uuid $personId, DateTimeImmutable $at, ?Uuid $by): void;

    /**
     * @return ReadableCollection<int, HomeworkReport>
     */
    public function allAbout(Uuid $personId): ReadableCollection;

    /**
     * @return ReadableCollection<int, HomeworkReport>
     */
    public function allBy(Uuid $accountId): ReadableCollection;
}
