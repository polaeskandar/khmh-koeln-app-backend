<?php

declare(strict_types=1);

namespace App\Context\Attendance\Domain\Repository;

use Symfony\Component\Uid\Uuid;

interface RemovalNoteRepositoryInterface
{
    /**
     * @param list<string> $sessionIds
     *
     * @return list<array{id: string, text: string, sessionId: string}>
     */
    public function notesOf(array $sessionIds, Uuid $exceptPerson): array;

    public function clear(string $id): void;
}
