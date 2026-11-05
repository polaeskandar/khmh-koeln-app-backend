<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Repository;

use Symfony\Component\Uid\Uuid;

interface LessonNoteRepositoryInterface
{
    /**
     * @param list<Uuid> $stageIds
     *
     * @return list<array{id: string, text: string, date: string, stageId: string}>
     */
    public function notesOf(array $stageIds): array;

    public function clear(string $id): void;
}
