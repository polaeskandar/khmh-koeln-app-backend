<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Repository;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\ValueObject\HomeworkCounts;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface HomeworkRepositoryInterface
{
    public function save(HomeworkTask $task): void;

    public function find(Uuid $id): ?HomeworkTask;

    /**
     * @return list<HomeworkTask>
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
     * @return list<HomeworkTask>
     */
    public function publishedInStages(array $stageIds): array;

    /**
     * @template T of object
     *
     * @param callable(HomeworkTask): T $work
     *
     * @return T
     */
    public function withLock(Uuid $id, callable $work): object;
}
