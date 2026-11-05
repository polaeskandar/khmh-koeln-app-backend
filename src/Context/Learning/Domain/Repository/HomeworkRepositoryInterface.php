<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Repository;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\Learning\Domain\ValueObject\HomeworkCounts;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface HomeworkRepositoryInterface
{
    public function save(HomeworkAssignment $assignment): void;

    /**
     * @param list<Uuid> $lessonIds
     *
     * @return ReadableCollection<string, HomeworkCounts>
     */
    public function countsByLesson(array $lessonIds): ReadableCollection;

    /**
     * @param list<HomeworkAssignment> $tasks
     *
     * @return ReadableCollection<int, HomeworkCompletion>
     */
    public function currentReportsOf(array $tasks): ReadableCollection;

    /**
     * @template T of object
     *
     * @param callable(HomeworkAssignment): T $work
     *
     * @return T
     */
    public function withLock(Uuid $id, callable $work): object;
}
