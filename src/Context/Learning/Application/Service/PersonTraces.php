<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Repository\LessonNoteRepositoryInterface;
use App\Context\Learning\Domain\Repository\PersonTraceRepositoryInterface;
use App\Library\Collections\Model\Collections;
use App\Platform\Privacy\Contract\PersonNotesInterface;
use App\Platform\Privacy\Contract\PersonReferencesInterface;
use App\Platform\Privacy\Model\ErasedPerson;
use App\Platform\Privacy\Model\ErasedPlace;
use App\Platform\Privacy\Model\FoundNote;
use App\Platform\Privacy\Model\NoteRef;
use Doctrine\Common\Collections\ArrayCollection;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class PersonTraces implements PersonReferencesInterface, PersonNotesInterface
{
    private const string LESSON_NOTE = 'lessonNote';

    public function __construct(
        private PersonTraceRepositoryInterface $traces,
        private LessonNoteRepositoryInterface $lessonNotes,
    ) {
    }

    /**
     * @return array<string, int>
     */
    #[Override]
    public function forgetPerson(ErasedPerson $person): array
    {
        return ['reports' => $this->traces->forget($person->id)];
    }

    /**
     * @return list<FoundNote>
     */
    #[Override]
    public function notesAbout(ErasedPerson $person): array
    {
        $stageIds = Collections::mapPresent(
            new ArrayCollection($person->places),
            static fn (ErasedPlace $place): ?Uuid => $place->stageId,
        )->getValues();

        return new ArrayCollection($this->lessonNotes->notesOf($stageIds))->map(
            static fn (array $note): FoundNote => new FoundNote(
                new NoteRef(self::LESSON_NOTE, $note['id']),
                $note['text'],
                $note['date'],
                meeting: null,
                stageId: Uuid::fromString($note['stageId']),
            ),
        )->getValues();
    }

    /**
     * @param list<NoteRef> $notes
     */
    #[Override]
    public function clearNotes(array $notes): void
    {
        $lessonNotes = new ArrayCollection($notes)->filter(
            static fn (NoteRef $note): bool => $note->where === self::LESSON_NOTE,
        );

        foreach ($lessonNotes as $note) {
            $this->lessonNotes->clear($note->id);
        }
    }
}
