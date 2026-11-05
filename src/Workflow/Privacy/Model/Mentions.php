<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Learning\Domain\ValueObject\LessonNote;
use App\Context\Meetings\Domain\ValueObject\MeetingNote;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\WholeWord;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class Mentions
{
    public const string LESSON_NOTE = 'lessonNote';

    /**
     * @param list<MeetingNote> $meetingNotes
     * @param list<LessonNote> $lessonNotes
     * @param ReadableCollection<int, Stage> $stages
     *
     * @return list<Mention>
     */
    public static function of(
        ErasablePerson $person,
        array $meetingNotes,
        array $lessonNotes,
        ReadableCollection $stages,
    ): array {
        $found = [...self::ofMeetings($meetingNotes), ...self::ofLessons($lessonNotes, $stages)];

        return new ArrayCollection($found)
            ->filter(static fn (Mention $mention): bool => self::names($mention, $person->names))
            ->getValues();
    }

    /**
     * @return list<Uuid>
     */
    public static function stageIdsOf(ErasablePerson $person): array
    {
        return Collections::mapPresent(
            $person->memberships,
            static fn (Membership $membership): ?Uuid => $membership->stageId(),
        )->getValues();
    }

    /**
     * @param list<MeetingNote> $notes
     *
     * @return list<Mention>
     */
    private static function ofMeetings(array $notes): array
    {
        return new ArrayCollection($notes)->map(
            static fn (MeetingNote $note): Mention => new Mention(
                $note->id,
                $note->where,
                $note->text,
                $note->date,
                $note->meeting,
            ),
        )->getValues();
    }

    /**
     * @param list<LessonNote> $notes
     * @param ReadableCollection<int, Stage> $stages
     *
     * @return list<Mention>
     */
    private static function ofLessons(array $notes, ReadableCollection $stages): array
    {
        return new ArrayCollection($notes)->map(
            static fn (LessonNote $note): Mention => new Mention(
                $note->id,
                self::LESSON_NOTE,
                $note->text,
                $note->date,
                self::stageNameOf($stages, $note->stageId),
            ),
        )->getValues();
    }

    /**
     * @param ReadableCollection<int, Stage> $stages
     */
    private static function stageNameOf(ReadableCollection $stages, string $stageId): ?string
    {
        $stage = $stages->filter(static fn (Stage $stage): bool => $stage->id()->toRfc4122() === $stageId)->getValues();

        return ($stage[0] ?? null)?->name()->value;
    }

    /**
     * @param list<string> $names
     */
    private static function names(Mention $mention, array $names): bool
    {
        return !new ArrayCollection($names)
            ->filter(static fn (string $name): bool => WholeWord::in($mention->text, $name))
            ->isEmpty();
    }
}
