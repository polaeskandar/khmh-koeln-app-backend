<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Repository;

use Symfony\Component\Uid\Uuid;

interface SessionNoteRepositoryInterface
{
    /**
     * @param list<Uuid> $scheduleIds
     *
     * @return list<array{id: string, where: string, text: string, date: string, meeting: string}>
     */
    public function notesOf(array $scheduleIds, Uuid $exceptPerson): array;

    public function clear(string $where, string $id): void;
}
