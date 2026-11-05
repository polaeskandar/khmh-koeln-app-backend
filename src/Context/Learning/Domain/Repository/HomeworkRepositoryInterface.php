<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Repository;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use Symfony\Component\Uid\Uuid;

interface HomeworkRepositoryInterface
{
    public function save(HomeworkAssignment $assignment): void;

    /**
     * @template T of object
     *
     * @param callable(HomeworkAssignment): T $work
     *
     * @return T
     */
    public function withLock(Uuid $id, callable $work): object;
}
