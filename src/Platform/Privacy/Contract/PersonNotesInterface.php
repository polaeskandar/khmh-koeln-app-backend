<?php

declare(strict_types=1);

namespace App\Platform\Privacy\Contract;

use App\Platform\Privacy\Model\ErasedPerson;
use App\Platform\Privacy\Model\FoundNote;
use App\Platform\Privacy\Model\NoteReference;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::NOTES_TAG)]
interface PersonNotesInterface
{
    public const string NOTES_TAG = 'app.person_notes';

    /**
     * @return list<FoundNote>
     */
    public function notesAbout(ErasedPerson $person): array;

    /**
     * @param list<NoteReference> $notes
     */
    public function clearNotes(array $notes): void;
}
