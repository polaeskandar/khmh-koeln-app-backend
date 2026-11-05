<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Repository;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\Learning\Domain\ValueObject\CurrentReport;
use DateTimeImmutable;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface HomeworkReportRepositoryInterface
{
    /**
     * @param list<HomeworkAssignment> $tasks
     *
     * @return ReadableCollection<int, HomeworkCompletion>
     */
    public function currentReportsOf(array $tasks): ReadableCollection;

    /**
     * @param list<Uuid> $personIds
     *
     * @return ReadableCollection<int, HomeworkCompletion>
     */
    public function currentReportsAbout(array $personIds): ReadableCollection;

    public function reportDone(Uuid $taskId, Uuid $personId, DateTimeImmutable $at, ?Uuid $by): bool;

    public function currentReport(Uuid $taskId, Uuid $personId): ?CurrentReport;

    public function undo(Uuid $taskId, Uuid $personId, DateTimeImmutable $at, ?Uuid $by): void;
}
