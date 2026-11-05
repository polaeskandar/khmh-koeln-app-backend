<?php

declare(strict_types=1);

namespace App\Context\Attendance\Application\Service;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Attendance\Domain\Repository\AttendanceRepositoryInterface;
use App\Context\Attendance\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\Attendance\Domain\Repository\RemovalNoteRepositoryInterface;
use App\Context\Attendance\Domain\ValueObject\RemovalNote;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AttendanceErasures
{
    public function __construct(
        private AttendanceRepositoryInterface $attendance,
        private PersonTraceRepositoryInterface $traces,
        private RemovalNoteRepositoryInterface $removalNotes,
    ) {
    }

    /**
     * @return list<Uuid>
     */
    public function presentAt(Uuid $personId): array
    {
        return $this->attendance
            ->ofPerson($personId)
            ->filter(static fn (Attendance $mark): bool => $mark->isCurrent())
            ->map(static fn (Attendance $mark): Uuid => $mark->sessionId())
            ->getValues();
    }

    public function forget(Uuid $personId): int
    {
        return $this->traces->forget($personId);
    }

    /**
     * @param list<Uuid> $sessionIds
     *
     * @return list<RemovalNote>
     */
    public function removalNotesIn(array $sessionIds, Uuid $exceptPerson): array
    {
        $ids = new ArrayCollection($sessionIds)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues();

        return new ArrayCollection($this->removalNotes->notesOf($ids, $exceptPerson))->map(
            static fn (array $note): RemovalNote => new RemovalNote($note['id'], $note['text'], $note['sessionId']),
        )->getValues();
    }

    public function clearRemovalNote(string $id): void
    {
        $this->removalNotes->clear($id);
    }
}
