<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Repository;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\ValueObject\HomeworkCounts;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface HomeworkRepositoryInterface
{
    public function save(HomeworkAssignment $assignment): void;

    public function find(Uuid $id): ?HomeworkAssignment;

    /**
     * @return list<HomeworkAssignment>
     */
    public function ofStage(Uuid $stageId): array;

    /**
     * @param list<Uuid> $lessonIds
     *
     * @return ReadableCollection<string, HomeworkCounts>
     */
    public function countsByLesson(array $lessonIds): ReadableCollection;

    /**
     * @param list<Uuid> $stageIds
     *
     * @return list<HomeworkAssignment>
     */
    public function publishedInStages(array $stageIds): array;

    /**
     * @template T of object
     *
     * @param callable(HomeworkAssignment): T $work
     *
     * @return T|null null when no such task exists
     */
    public function withLock(Uuid $id, callable $work): ?object;
}
