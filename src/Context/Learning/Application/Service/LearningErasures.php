<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Repository\LessonNoteRepositoryInterface;
use App\Context\Learning\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\LessonNote;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class LearningErasures
{
    public function __construct(
        private PersonTraceRepositoryInterface $traces,
        private LessonNoteRepositoryInterface $lessonNotes,
    ) {
    }

    public function forget(Uuid $childId): int
    {
        return $this->traces->forget($childId);
    }

    /**
     * @param list<Uuid> $stageIds
     *
     * @return list<LessonNote>
     */
    public function lessonNotesOf(array $stageIds): array
    {
        return new ArrayCollection($this->lessonNotes->notesOf($stageIds))->map(
            static fn (array $note): LessonNote => new LessonNote(
                $note['id'],
                $note['text'],
                $note['date'],
                $note['stageId'],
            ),
        )->getValues();
    }

    public function clearLessonNote(string $id): void
    {
        $this->lessonNotes->clear($id);
    }
}
